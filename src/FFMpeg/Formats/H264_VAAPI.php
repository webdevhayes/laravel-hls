<?php

declare(strict_types=1);

/*
 * This file is part of PHP-FFmpeg.
 *
 * (c) Alchemy <info@alchemy.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AchyutN\LaravelHLS\FFMpeg\Formats;

use FFMpeg\Format\Video\DefaultVideo;

/**
 * The X264 video format.
 */
final class H264_VAAPI extends DefaultVideo
{
    /** @var bool */
    private $bframesSupport = true;

    /** @var int */
    private $passes = 2;

    public function __construct($audioCodec = 'copy')
    {
        $this->setAudioCodec($audioCodec);
    }

    /**
     * {@inheritDoc}
     */
    public function supportBFrames()
    {
        return $this->bframesSupport;
    }

    /**
     * @return H264_VAAPI
     */
    public function setBFramesSupport($support)
    {
        $this->bframesSupport = $support;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getAvailableAudioCodecs()
    {
        return ['copy', 'aac', 'libvo_aacenc', 'libfaac', 'libmp3lame', 'libfdk_aac'];
    }

    /**
     * {@inheritDoc}
     */
    public function getAvailableVideoCodecs()
    {
        return ['h264_vaapi'];
    }

    /**
     * @return H264_VAAPI
     */
    public function setPasses($passes)
    {
        $this->passes = $passes;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getPasses()
    {
        return $this->getKiloBitrate() === 0 ? 1 : $this->passes;
    }

    /**
     * @return int
     */
    public function getModulus()
    {
        return 2;
    }
}
