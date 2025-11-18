<?php

namespace Signify\Tasks;

use DateInterval;
use Signify\Jobs\RemoveOldCSPViolationsJob;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class RemoveOldCSPViolationsTask extends BuildTask
{
    protected static string $commandName = 'remove-old-csp-violations';

    protected string $title = 'Remove old CSP violation reports';

    protected static string $description = 'Queue a job to delete CSP violation reports older than your configured retention window.';

    /**
     * {@inheritDoc}
     * @see \SilverStripe\Dev\BuildTask::execute()
     */
    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $deletionJob = new RemoveOldCSPViolationsJob();

        $jobService = Injector::inst()->get(QueuedJobService::class);
        $jobId = $jobService->queueJob($deletionJob);

        $output->writeln(sprintf('Job queued with ID %s', $jobId ?? 'unknown'));

        return Command::SUCCESS;
    }

    public static function getDescription(): string
    {
        $base = static::$description;
        $retention = static::getRetentionSummary();
        if ($retention) {
            $base .= " Current retention window: {$retention}.";
        }
        return $base;
    }

    protected static function getRetentionSummary(): ?string
    {
        // Map DateInterval fields to text names. Order is significant.
        static $parts = [
            'y' => 'year',
            'm' => 'month',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
            'f' => 'microsecond',
        ];

        $retention = Config::inst()->get(RemoveOldCSPViolationsJob::class, 'retention_period');
        if (!$retention) {
            return null;
        }

        try {
            $retention = new DateInterval($retention);
        } catch (\Exception $e) {
            return $retention;
        }

        $duration_parts = [];
        foreach ($parts as $field => $label) {
            if ($retention->$field != 0) {
                // Microseconds are a fraction of a second. Everything else is defined in terms of itself.
                $value = $field === 'f'
                    ? round($retention->$field * 1000000.0, 0, PHP_ROUND_HALF_UP)
                    : $retention->$field;

                // Cheap and nasty pluralisation.
                $duration_parts[] = $value . ' ' . $label . ($value === 1 ? '' : 's');
            }
        }

        // Convert to string e.g. "12 hours, 30 minutes and 10 seconds"
        if (count($duration_parts) > 1) {
            $last = array_pop($duration_parts);
            $duration_string = implode(', ', $duration_parts) . ' and ' . $last;
        } else {
            $duration_string = reset($duration_parts);
        }

        if (!$duration_parts) {
            return null;
        }

        return $duration_string;
    }

    public function isEnabled(): bool
    {
        return parent::isEnabled() && class_exists(QueuedJobService::class);
    }
}
