<?php

namespace App\FFMpeg\Formats;

use FFMpeg\Format\Video\DefaultVideo;

class H264_VAAPI extends DefaultVideo
{
    public function __construct($audioCodec = 'copy')
    {
        $this->setAudioCodec($audioCodec);
    }

    /**
     * We borrow this from the X264 class to allow for flexible
     * audio encoding alongside our hardware-accelerated video.
     */
    public function getAvailableAudioCodecs(): array
    {
        return ['copy', 'aac', 'libvo_aacenc', 'libfaac', 'libmp3lame', 'libfdk_aac'];
    }

    public function getAvailableVideoCodecs(): array
    {
        return ['h264_vaapi'];
    }

    /**
     * Hardware encoders generally do not use the same multi-pass
     * system as software encoders. We'll stick to a single pass.
     */
    public function getPasses(): int
    {
        return 1;
    }

    /**
     * This ensures video dimensions are divisible by 2,
     * which is a requirement for the H.264 codec.
     */
    public function getModulus(): int
    {
        return 2;
    }
}
