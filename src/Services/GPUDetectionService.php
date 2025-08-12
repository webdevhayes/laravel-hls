<?php

declare(strict_types=1);

namespace AchyutN\LaravelHLS\Services;

use AchyutN\LaravelHLS\Traits\DebugLoggable;
use Exception;

final class GPUDetectionService
{
    use DebugLoggable;
    // Configuration constants
    private const GPU_TYPES = [
        'APPLE' => 'apple',
        'NVIDIA' => 'nvidia',
        'INTEL' => 'intel',
        'CPU' => 'cpu'
    ];

    private const DEFAULT_MIN_MEMORY_MB = 500;
    private const DEFAULT_MAX_TEMP = 85;

    private ?string $ffmpegPath = null;
    private ?string $smiPath = null;

    /**
     * Detect the best available GPU for video encoding.
     */
    public function detectBestGPU(): string
    {
        $this->debugLog("🔍 Detecting best available GPU...");

        if (!$this->canDetectGPU()) {
            return self::GPU_TYPES['CPU'];
        }

        $encoders = $this->getAvailableEncoders();

        if ($this->hasAppleSilicon($encoders)) {
            return self::GPU_TYPES['APPLE'];
        }

        if ($this->hasNvidiaGPU($encoders)) {
            return self::GPU_TYPES['NVIDIA'];
        }

        if ($this->hasIntelGPU($encoders)) {
            return self::GPU_TYPES['INTEL'];
        }

        $this->debugLog("❌ No compatible GPU found. Using CPU.", 'warning');
        return self::GPU_TYPES['CPU'];
    }

    /**
     * Check if GPU detection is possible on this system.
     */
    private function canDetectGPU(): bool
    {
        if (!function_exists('shell_exec')) {
            $this->debugLog('❌ GPU detection failed: shell_exec function not available.', 'warning');
            return false;
        }

        $this->ffmpegPath = $this->findBinary('ffmpeg');
        if (empty($this->ffmpegPath)) {
            $this->debugLog('❌ GPU detection failed: ffmpeg binary not found.', 'warning');
            return false;
        }

        $this->debugLog("✅ FFmpeg found at: " . $this->ffmpegPath);
        return true;
    }

    /**
     * Get available video encoders from FFmpeg.
     */
    private function getAvailableEncoders(): string
    {
        try {
            return shell_exec($this->ffmpegPath . ' -hide_banner -encoders 2>&1');
        } catch (Exception $e) {
            $this->debugLog('❌ Failed to get encoders: ' . $e->getMessage(), 'error');
            return '';
        }
    }

    /**
     * Check if Apple Silicon (VideoToolbox) is available.
     */
    private function hasAppleSilicon(string $encoders): bool
    {
        if (str_contains($encoders, 'h264_videotoolbox')) {
            $this->debugLog("🍎 Apple Silicon (VideoToolbox) detected!");
            return true;
        }
        return false;
    }

    /**
     * Check if NVIDIA GPU is available and ready for use.
     */
    private function hasNvidiaGPU(string $encoders): bool
    {
        if (!str_contains($encoders, 'h264_nvenc')) {
            return false;
        }

        $this->debugLog("🚀 NVIDIA GPU (NVENC) detected!");

        if ($this->isNvidiaGPUReady()) {
            $this->debugLog("✅ NVIDIA GPU is available and ready for use!");
            return true;
        }

        $this->debugLog("❌ NVIDIA GPU check failed: Memory or temperature issues.", 'warning');
        return false;
    }

    /**
     * Check if Intel GPU (VAAPI) is available and ready for use.
     */
    private function hasIntelGPU(string $encoders): bool
    {
        if (!str_contains($encoders, 'h264_vaapi')) {
            return false;
        }

        $this->debugLog("💻 Intel GPU (VAAPI) detected!");

        if ($this->isIntelGPUReady()) {
            $this->debugLog("✅ Intel GPU is available and ready for use!");
            return true;
        }

        $this->debugLog("❌ Intel GPU check failed: Memory or temperature issues.", 'warning');
        return false;
    }

    /**
     * Check if NVIDIA GPU is ready (sufficient memory and acceptable temperature).
     */
    private function isNvidiaGPUReady(): bool
    {
        return $this->hasSufficientGPUMemory() && $this->isGPUTempOK();
    }

    /**
     * Check if Intel GPU is ready (sufficient memory and acceptable temperature).
     */
    private function isIntelGPUReady(): bool
    {
        // For Intel VAAPI, we'll assume it's ready if the encoder is available
        // Intel GPUs typically don't have the same memory/temperature constraints as discrete GPUs
        return true;
    }

    /**
     * Check if NVIDIA GPU has sufficient free memory for encoding.
     */
    private function hasSufficientGPUMemory(): bool
    {
        if (!$this->canCheckNvidiaGPU()) {
            return true; // Assume OK if we can't check
        }

        $freeMemory = $this->getNvidiaGPUMemory();
        if ($freeMemory === null) {
            return true; // Assume OK if we can't read memory
        }

        $minRequiredMemory = config('hls.gpu_min_memory_mb', self::DEFAULT_MIN_MEMORY_MB);

        if ($freeMemory < $minRequiredMemory) {
            $this->debugLog("GPU check failed: Insufficient free memory. Found: {$freeMemory}MB, Required: {$minRequiredMemory}MB.", 'warning');
            return false;
        }

        return true;
    }

    /**
     * Check if NVIDIA GPU temperature is within acceptable limits.
     */
    private function isGPUTempOK(): bool
    {
        if (!$this->canCheckNvidiaGPU()) {
            return true; // Assume OK if we can't check
        }

        $temperature = $this->getNvidiaGPUTemperature();
        if ($temperature === null) {
            return true; // Assume OK if we can't read temperature
        }

        $maxTemp = config('hls.gpu_max_temp', self::DEFAULT_MAX_TEMP);

        if ($temperature >= $maxTemp) {
            $this->debugLog("GPU check failed: Temperature too high. Current: {$temperature}°C, Threshold: {$maxTemp}°C.", 'warning');
            return false;
        }

        return true;
    }

    /**
     * Check if Intel GPU temperature is within acceptable limits.
     */
    private function isIntelTempOK(): bool
    {
        // Intel integrated GPUs typically don't have temperature sensors accessible via command line
        // We'll assume they're OK since they're designed to work within thermal limits
        return true;
    }

    /**
     * Check if we can query NVIDIA GPU information.
     */
    private function canCheckNvidiaGPU(): bool
    {
        $this->smiPath = $this->findBinary('nvidia-smi', ['C:\Program Files\NVIDIA Corporation\NVSMI\nvidia-smi.exe']);
        return !empty($this->smiPath);
    }

    /**
     * Check if we can query Intel GPU information.
     */
    private function canCheckIntelGPU(): bool
    {
        // Intel VAAPI doesn't have a standard monitoring tool like nvidia-smi
        // We'll rely on FFmpeg encoder detection instead
        return false;
    }

    /**
     * Get NVIDIA GPU free memory in MB.
     */
    private function getNvidiaGPUMemory(): ?int
    {
        $output = shell_exec($this->smiPath . ' --query-gpu=memory.free --format=csv,noheader,nounits 2>&1');
        $memory = trim($output);

        return is_numeric($memory) ? (int)$memory : null;
    }

    /**
     * Get Intel GPU free memory in MB.
     */
    private function getIntelGPUMemory(): ?int
    {
        // Intel integrated GPUs share system memory, so we can't easily query dedicated GPU memory
        // We'll return null to indicate this information is not available
        return null;
    }

    /**
     * Get NVIDIA GPU temperature in Celsius.
     */
    private function getNvidiaGPUTemperature(): ?int
    {
        $output = shell_exec($this->smiPath . ' --query-gpu=temperature.gpu --format=csv,noheader,nounits 2>&1');
        $temp = trim($output);

        return is_numeric($temp) ? (int)$temp : null;
    }

    /**
     * Get Intel GPU temperature in Celsius.
     */
    private function getIntelGPUTemperature(): ?int
    {
        // Intel integrated GPUs typically don't expose temperature via command line
        // We'll return null to indicate this information is not available
        return null;
    }

    /**
     * Find a binary in the system PATH.
     */
    private function findBinary(string $name, array $customPaths = []): string
    {
        $paths = array_merge([$name], $customPaths, ['/usr/bin/' . $name, '/usr/local/bin/' . $name]);
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $testCommand = $isWindows ? 'where' : 'command -v';

        foreach ($paths as $path) {
            $output = shell_exec("$testCommand $path 2>&1");
            if (!empty($output) && !str_contains($output, 'not found')) {
                return trim($path);
            }
        }

        return '';
    }


}
