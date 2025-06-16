<?php

namespace ApiLog\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Thelia\Command\ContainerAwareCommand;

#[AsCommand(
    name: 'app:api-log:report',
    description: 'Rapport sur les appels API par type (HTTP, APIP).',
)]
class GenerateReportCommand extends ContainerAwareCommand
{
    protected function configure(): void
    {
        $this
            ->addArgument('filename-prefix', InputArgument::OPTIONAL, 'Nom du fichier contenant les logs (ex: api_log)')
            ->addArgument('log-dir', InputArgument::OPTIONAL, 'Répertoire contenant les logs (ex: var/log)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);
        $logDir = $input->getArgument('log-dir') ? rtrim($input->getArgument('log-dir'), '/') : 'var/log';
        $filenamePrefix = $input->getArgument('filename-prefix') ?? 'api_log';

        if (!is_dir($logDir)) {
            $style->error("Le répertoire $logDir n'existe pas.");
            return Command::FAILURE;
        }

        $finder = new Finder();
        $finder->files()->in($logDir)->name('/^'.$filenamePrefix.'-\d{4}-\d{2}-\d{2}\.log$/');

        if (!$finder->hasResults()) {
            $style->warning('Aucun fichier log \''.$filenamePrefix.'-YYYY-MM-DD.log\' trouvé dans $logDir.');
            return Command::SUCCESS;
        }

        $globalStats = [];

        foreach ($finder as $file) {
            $filename = $file->getFilename();
            $date = substr($filename, strlen($filename) - 14, 10);

            if (!isset($globalStats[$date])) {
                $globalStats[$date] = [];
            }

            foreach (file($file->getRealPath()) as $line) {
                $method = self::getMethod($line);
                $type = match (true) {
                    str_contains($line, '[HTTP]') => 'HTTP',
                    str_contains($line, '[APIP]') => 'APIP',
                    default => 'UNKNOWN',
                };
                if ($type === 'UNKNOWN') {
                    continue;
                }
                $globalStats[$date][$type]['total'] = ($globalStats[$date][$type]['total'] ?? 0) + 1;

                $status = 'unknown';
                if (preg_match('/"status":\s*(\d+)/', $line, $matches)) {
                    $status = $matches[1];
                }
                $globalStats[$date][$type]['methods'][$method]['total'] = ($globalStats[$date][$type]['methods'][$method]['total'] ?? 0) + 1;
                $globalStats[$date][$type]['methods'][$method]['statuses'][$status] = ($globalStats[$date][$type]['methods'][$method]['statuses'][$status] ?? 0) + 1;
            }
        }

        ksort($globalStats);

        $rows = [];
        foreach ($globalStats as $date => $dataByType) {
            foreach (array_keys($dataByType) as $type) {
                foreach ($dataByType[$type]['methods'] as $method => $methodData) {
                    $rows[] = [
                        $date,
                        $type,
                        $method,
                        $methodData['total'],
                        self::formatStatuses($methodData['statuses']),
                    ];
                }
                $rows[] = [
                    $date,
                    $type,
                    'Total',
                    $dataByType[$type]['total']
                ];
            }
            $rows[] = [''];
        }

        $style->title('Statistiques API par date, type et méthode');
        $style->table(
            ['Date', 'Type', 'Méthode', 'Total', 'Statuts'],
            $rows
        );

        return Command::SUCCESS;
    }

    private static function getMethod(string $line): string
    {
        if (preg_match('/RESPONSE\s+([A-Z]+)/i', $line, $methodMatch)) {
            return strtoupper($methodMatch[1]);
        }
        return 'UNKNOWN';
    }

    private static function formatStatuses(array $statuses): string
    {
        if (empty($statuses)) {
            return '-';
        }
        $parts = [];
        foreach ($statuses as $code => $count) {
            $parts[] = "$code: $count";
        }
        return implode(', ', $parts);
    }
}
