<?php

declare(strict_types=1);

namespace AchyutN\LaravelHLS\Jobs;

use AchyutN\LaravelHLS\Actions\CheckForDatabaseColumns;
use AchyutN\LaravelHLS\Actions\ConvertToHLS;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

final class QueueHLSConversion implements ShouldQueue
{
    use Queueable;

    /**
     * Indicate if the job should be marked as failed on timeout.
     */
    public bool $failOnTimeout = false;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 0; // No timeout, can run indefinitely

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Model $model
    ) {
        //
    }

    /**
     * Execute the job.
     *
     * @throws Exception
     */
    public function handle(): void
    {
        try {
            config('laravel-ffmpeg.temporary_files_encrypted_hls', config('hls.temp_hls_storage_path'));
            config('laravel-ffmpeg.temporary_files_root', config('hls.temp_storage_path'));

            CheckForDatabaseColumns::handle($this->model);

            $original_path = $this->model->getVideoPath();
            $folderName = uuid_create();

            ConvertToHLS::convertToHLS(
                $original_path,
                $folderName,
                $this->model
            );

            $this->model->setHlsPath($folderName);
            $this->model->saveQuietly();

            if (config('hls.delete_original_file_after_conversion')) {
                Storage::disk($this->model->getVideoDisk())->delete($original_path);
                $this->model->setVideoPath(null);
            }

            $this->model->saveQuietly();
        } catch (Exception $e) {
            // Ensure cleanup happens even if the job fails
            FFMpeg::cleanupTemporaryFiles();

            Log::error('HLS conversion job failed', [
                'model_id' => $this->model->getKey(),
                'model_type' => get_class($this->model),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        // Ensure cleanup happens when the job fails
        FFMpeg::cleanupTemporaryFiles();

        Log::error('HLS conversion job failed and will not be retried', [
            'model_id' => $this->model->getKey(),
            'model_type' => get_class($this->model),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
