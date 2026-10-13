<?php

namespace App\Services\Scoring;

/**
 * Condensed, paraphrased summaries of the public IELTS band descriptors, given to the AI examiner.
 */
class BandDescriptors
{
    public const SPEAKING = <<<'TEXT'
FLUENCY & COHERENCE
9: speaks fluently, only rare hesitation (content-related); fully coherent, topics developed fully.
8: fluent, occasional repetition/self-correction; hesitation usually content-related; develops topics coherently.
7: speaks at length without noticeable effort; some language-related hesitation, repetition or self-correction; flexible use of connectives and discourse markers.
6: willing to speak at length but loses coherence at times due to repetition, self-correction or hesitation; range of connectives not always appropriate.
5: maintains flow but relies on repetition, self-correction and/or slow speech; overuses certain connectives; simple speech fluent, complex communication causes problems.
4: cannot respond without noticeable pauses; slow speech, frequent repetition; links basic sentences with repetitious simple connectives.

LEXICAL RESOURCE
9: full flexibility and precision; idiomatic language naturally and accurately.
8: wide resource used readily and flexibly; skilful less common and idiomatic vocabulary, occasional inaccuracy; paraphrases effectively.
7: flexible on a variety of topics; some less common and idiomatic vocabulary, awareness of style and collocation, some inappropriate choices; paraphrases effectively.
6: wide enough to discuss topics at length and make meaning clear despite inappropriacies; generally paraphrases successfully.
5: manages familiar and unfamiliar topics with limited flexibility; attempts paraphrase with mixed success.
4: sufficient for familiar topics only; basic meaning on unfamiliar topics, frequent errors in word choice; rarely paraphrases.

GRAMMATICAL RANGE & ACCURACY
9: full range of structures naturally and appropriately; consistently accurate apart from native-speaker-like slips.
8: wide range used flexibly; majority of sentences error-free, occasional inappropriacies or non-systematic errors.
7: range of complex structures with some flexibility; frequently error-free sentences, though some errors persist.
6: mix of simple and complex structures, limited flexibility; frequent errors in complex structures that rarely impede communication.
5: basic sentence forms reasonably accurate; limited range of complex structures that usually contain errors.
4: basic sentence forms, some correct simple sentences, subordinate structures rare; errors frequent, may cause misunderstanding.

PRONUNCIATION
9: full range of features with precision and subtlety; effortless to understand; accent has no effect on intelligibility.
8: wide range of features; flexible, sustained use; easy to understand, accent has minimal effect.
7: all positive features of 6 and some of 8.
6: range of features with mixed control; some effective intonation and stress; generally understood though mispronunciation of words/sounds reduces clarity at times.
5: all positive features of 4 and some of 6.
4: limited range of features; attempts control but lapses frequent; mispronunciations frequent and cause some difficulty for the listener.
TEXT;

    public const WRITING = <<<'TEXT'
TASK ACHIEVEMENT (Task 1) / TASK RESPONSE (Task 2)
9: fully satisfies all requirements; fully developed, clear position (T2) or clear overview with key features fully illustrated (T1).
8: covers all requirements sufficiently; well-developed with relevant, extended, supported ideas; key features clearly highlighted.
7: addresses all parts; clear position throughout (T2) / clear overview (T1); main ideas extended and supported but may over-generalise or lack focus.
6: addresses all parts though some more fully than others; relevant position but conclusions may be unclear or repetitive; overview present (T1) but details may be irrelevant or inaccurate.
5: addresses the task only partially; position not always clear; limited development; no clear overview (T1) or format inappropriate.
4: responds minimally or tangentially; position unclear; ideas difficult to identify, irrelevant or unsupported.

COHERENCE & COHESION
9: cohesion attracts no attention; paragraphing skilfully managed.
8: sequences information logically; manages all aspects of cohesion well; paragraphs sufficiently and appropriately.
7: logically organised with clear progression; range of cohesive devices with some under/over-use; clear central topic in each paragraph.
6: coherent arrangement with overall progression; cohesive devices effective but cohesion within/between sentences may be faulty or mechanical; paragraphing not always logical.
5: some organisation but lacks overall progression; inadequate, inaccurate or overused cohesive devices; paragraphing may be inadequate.
4: information not arranged coherently, no clear progression; basic cohesive devices inaccurate or repetitive; may not write in paragraphs.

LEXICAL RESOURCE
9: wide range with very natural, sophisticated control; rare minor slips.
8: wide range used fluently and flexibly to convey precise meaning; skilful uncommon items; occasional errors in word choice/collocation.
7: sufficient range for flexibility and precision; less common items with awareness of style and collocation; occasional errors in word choice, spelling or word formation.
6: adequate range; attempts less common vocabulary with some inaccuracy; errors in spelling/word formation that do not impede communication.
5: limited range, minimally adequate; noticeable errors in spelling/word formation that may cause difficulty.
4: basic vocabulary used repetitively or inappropriately; limited control of word formation/spelling that may strain the reader.

GRAMMATICAL RANGE & ACCURACY
9: wide range with full flexibility and accuracy; rare minor slips.
8: wide range; majority of sentences error-free; only very occasional errors or inappropriacies.
7: variety of complex structures; frequent error-free sentences; good control though some errors remain.
6: mix of simple and complex sentence forms; some errors in grammar and punctuation that rarely reduce communication.
5: limited range; attempts complex sentences that tend to be less accurate than simple ones; frequent errors that can cause difficulty.
4: very limited range, rare subordinate clauses; some accurate structures but errors predominate; punctuation often faulty.
TEXT;
}
