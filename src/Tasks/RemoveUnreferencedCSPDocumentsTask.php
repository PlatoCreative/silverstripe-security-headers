<?php

namespace Signify\Tasks;

use Signify\Jobs\RemoveUnreferencedCSPDocumentJob;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class RemoveUnreferencedCSPDocumentsTask extends BuildTask
{
    protected static string $commandName = 'remove-unreferenced-csp-documents';

    protected string $title = 'Remove unreferenced CSP Document URIs';

    protected static string $description =
    'Queue a job to remove CSP Document URIs that are no longer referenced by violation reports.';

    /**
     * {@inheritDoc}
     * @see \SilverStripe\Dev\BuildTask::execute()
     */
    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $deletionJob = new RemoveUnreferencedCSPDocumentJob();

        $jobService = Injector::inst()->get(QueuedJobService::class);
        $jobId = $jobService->queueJob($deletionJob);

        $output->writeln(sprintf('Job queued with ID %s', $jobId ?? 'unknown'));

        return Command::SUCCESS;
    }

    public function isEnabled(): bool
    {
        return parent::isEnabled() && class_exists(QueuedJobService::class);
    }
}
