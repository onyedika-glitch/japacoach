"""Japa Coach speech service: faster-whisper transcription and Kokoro text-to-speech.

Runs on a small CPU-only VPS, so heavy work is serialised behind a lock and models load once at startup.
"""

import io
import logging
import os
import subprocess
import tempfile
import threading
from contextlib import asynccontextmanager

import numpy as np
import soundfile as sf
from fastapi import Depends, FastAPI, File, Header, HTTPException, UploadFile
from fastapi.responses import Response
from pydantic import BaseModel, Field

logging.basicConfig(level=logging.INFO)
log = logging.getLogger("speech")

WHISPER_MODEL = os.getenv("WHISPER_MODEL", "base.en")
WHISPER_THREADS = int(os.getenv("WHISPER_THREADS", "1"))
WHISPER_BEAM_SIZE = int(os.getenv("WHISPER_BEAM_SIZE", "1"))
KOKORO_MODEL = os.getenv("KOKORO_MODEL", "/opt/kokoro/kokoro-v1.0.int8.onnx")
KOKORO_VOICES = os.getenv("KOKORO_VOICES", "/opt/kokoro/voices-v1.0.bin")
SERVICE_TOKEN = os.getenv("SPEECH_SERVICE_TOKEN", "")
MAX_UPLOAD_BYTES = 20 * 1024 * 1024
SAMPLE_RATE = 24000

models: dict = {}
# One heavy job at a time: a single vCPU gains nothing from parallel inference and RAM is tight.
work_lock = threading.Lock()


@asynccontextmanager
async def lifespan(_: FastAPI):
    from faster_whisper import WhisperModel
    from kokoro_onnx import Kokoro

    log.info("Loading whisper model %s", WHISPER_MODEL)
    models["whisper"] = WhisperModel(WHISPER_MODEL, device="cpu", compute_type="int8", cpu_threads=WHISPER_THREADS)
    log.info("Loading Kokoro model %s", KOKORO_MODEL)
    models["kokoro"] = Kokoro(KOKORO_MODEL, KOKORO_VOICES)
    yield
    models.clear()


app = FastAPI(title="Japa Coach speech", lifespan=lifespan)


def require_token(x_service_token: str = Header(default="")) -> None:
    if not SERVICE_TOKEN or x_service_token != SERVICE_TOKEN:
        raise HTTPException(status_code=401, detail="Invalid service token")


@app.get("/health")
def health() -> dict:
    return {"status": "ok", "whisper": WHISPER_MODEL, "ready": "whisper" in models and "kokoro" in models}


@app.post("/transcribe", dependencies=[Depends(require_token)])
def transcribe(audio: UploadFile = File(...)) -> dict:
    data = audio.file.read(MAX_UPLOAD_BYTES + 1)
    if len(data) > MAX_UPLOAD_BYTES:
        raise HTTPException(status_code=413, detail="Audio file too large")
    if not data:
        raise HTTPException(status_code=422, detail="Empty audio file")

    suffix = os.path.splitext(audio.filename or "")[1] or ".webm"
    with tempfile.NamedTemporaryFile(suffix=suffix) as tmp:
        tmp.write(data)
        tmp.flush()
        with work_lock:
            try:
                segments, info = models["whisper"].transcribe(
                    tmp.name,
                    language="en",
                    beam_size=WHISPER_BEAM_SIZE,
                    word_timestamps=True,
                    vad_filter=True,
                    # Keep hesitations in the transcript: they matter for fluency scoring.
                    initial_prompt="Umm, let me think, uh, well, I think that...",
                    condition_on_previous_text=False,
                )
                segments = list(segments)
            except Exception as exc:  # noqa: BLE001 - decoding errors come from many libraries
                log.exception("Transcription failed")
                raise HTTPException(status_code=422, detail=f"Could not decode audio: {exc}") from exc

    words = [
        {"word": w.word.strip(), "start": round(w.start, 2), "end": round(w.end, 2), "probability": round(w.probability, 3)}
        for segment in segments
        for w in (segment.words or [])
        if w.word.strip()
    ]

    return {
        "text": " ".join(segment.text.strip() for segment in segments).strip(),
        "duration": round(float(info.duration), 2),
        "words": words,
    }


class TtsLine(BaseModel):
    voice: str = Field(min_length=2, max_length=40)
    text: str = Field(min_length=1, max_length=5000)


class TtsRequest(BaseModel):
    lines: list[TtsLine] = Field(min_length=1, max_length=400)
    speed: float = Field(default=1.0, ge=0.5, le=1.5)
    format: str = Field(default="mp3", pattern="^(mp3|wav)$")


@app.post("/tts", dependencies=[Depends(require_token)])
def tts(request: TtsRequest) -> Response:
    kokoro = models["kokoro"]
    available = set(kokoro.get_voices())
    pieces: list[np.ndarray] = []
    previous_voice = None

    with work_lock:
        for line in request.lines:
            if line.voice not in available:
                raise HTTPException(status_code=422, detail=f"Unknown voice {line.voice}")
            if previous_voice is not None:
                # Longer pause when the speaker changes, like a natural conversation.
                gap = 0.55 if line.voice != previous_voice else 0.3
                pieces.append(np.zeros(int(SAMPLE_RATE * gap), dtype=np.float32))
            lang = "en-gb" if line.voice.startswith("b") else "en-us"
            samples, _ = kokoro.create(line.text, voice=line.voice, speed=request.speed, lang=lang)
            pieces.append(samples.astype(np.float32))
            previous_voice = line.voice

    wav = io.BytesIO()
    sf.write(wav, np.concatenate(pieces), SAMPLE_RATE, format="WAV")

    if request.format == "wav":
        return Response(content=wav.getvalue(), media_type="audio/wav")

    return Response(content=to_mp3(wav.getvalue()), media_type="audio/mpeg")


def to_mp3(wav_bytes: bytes) -> bytes:
    """Encode to compact mono MP3 so listening tests download quickly on mobile data."""
    result = subprocess.run(
        ["ffmpeg", "-hide_banner", "-loglevel", "error", "-f", "wav", "-i", "pipe:0",
         "-ac", "1", "-codec:a", "libmp3lame", "-b:a", "64k", "-f", "mp3", "pipe:1"],
        input=wav_bytes,
        capture_output=True,
        check=False,
    )
    if result.returncode != 0:
        raise HTTPException(status_code=500, detail="MP3 encoding failed")
    return result.stdout
