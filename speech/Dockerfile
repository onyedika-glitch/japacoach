FROM python:3.12-slim

ENV PYTHONDONTWRITEBYTECODE=1 PYTHONUNBUFFERED=1 \
    HF_HOME=/cache/hf \
    KOKORO_MODEL=/opt/kokoro/kokoro-v1.0.int8.onnx \
    KOKORO_VOICES=/opt/kokoro/voices-v1.0.bin

RUN apt-get update \
    && apt-get install -y --no-install-recommends ffmpeg curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY requirements.txt .
RUN pip install --no-cache-dir --default-timeout=300 --retries 10 -r requirements.txt

# Kokoro int8 model (~90 MB) and voices; Whisper weights download into the /cache volume on first start.
RUN mkdir -p /opt/kokoro /cache/hf \
    && curl -fsSL -o /opt/kokoro/kokoro-v1.0.int8.onnx https://github.com/thewh1teagle/kokoro-onnx/releases/download/model-files-v1.0/kokoro-v1.0.int8.onnx \
    && curl -fsSL -o /opt/kokoro/voices-v1.0.bin https://github.com/thewh1teagle/kokoro-onnx/releases/download/model-files-v1.0/voices-v1.0.bin

COPY app.py .

EXPOSE 8100
HEALTHCHECK --interval=30s --timeout=5s --start-period=120s CMD curl -fs http://localhost:8100/health || exit 1
CMD ["uvicorn", "app:app", "--host", "0.0.0.0", "--port", "8100", "--workers", "1"]
