<?php

namespace RCV\Core\Console\Commands;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Console\Prohibitable;
use Illuminate\Console\ConfirmableTrait;
use function Laravel\Prompts\multisearch;
use RCV\Core\Contracts\VerifiableCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Illuminate\Contracts\Console\PromptsForMissingInput;

abstract class BaseCommand extends Command implements PromptsForMissingInput{
    use ConfirmableTrait;
    use Prohibitable;

    public const ALL = 'All';

    public function __construct()
    {
        parent::__construct();

        $this->getDefinition()->addOption(new InputOption(
            name: strtolower(self::ALL),
            shortcut: 'a',
            mode: InputOption::VALUE_NONE,
            description: 'Check all Modules',
        ));

       

        if ($this instanceof VerifiableCommand) {
            $this->configureConfirmable();
        }
    }

    abstract public function executeAction($name);

    public function getInfo(): ?string
    {
        return null;
    }

    public function getConfirmableLabel(): ?string
    {
        return 'Application In Production';
    }

    public function getConfirmableCallback(): Closure|bool|null
    {
        return null;
    }

    public function handle()
    {
        if ($this instanceof VerifiableCommand) {
            if ($this->isProhibited() ||
                ! $this->confirmToProceed($this->getConfirmableLabel(), $this->getConfirmableCallback())) {
                return Command::FAILURE;
            }
        }

        if (! is_null($info = $this->getInfo())) {
            $this->components->info($info);
        }

        $modules = (array) $this->argument('module');

        foreach ($modules as $module) {
            $this->executeAction($module);
        }
    }

    protected function promptForMissingArguments(InputInterface $input, OutputInterface $output): void
    {
        $modules = $this->hasOption('direction')
            ? array_keys(app('modules')->getOrdered($input->hasOption('direction')))
            : array_keys(app('modules')->all());

        if ($input->getOption(strtolower(self::ALL))) {
            $input->setArgument('module', $modules);
            return;
        }

        if (!empty($input->getArgument('module'))) {
            return;
        }

        $selected_item = multisearch(
            label: 'Select Modules',
            options: function (string $search_value) use ($modules) {
                return collect([self::ALL, ...$modules])
                    ->when(strlen($search_value) > 0, function (Collection &$modules) use ($search_value) {
                        return $modules->filter(fn ($item) => str_contains(strtolower($item), strtolower($search_value)));
                    })->values()->toArray();
            },
            required: 'You must select at least one module',
        );

        $input->setArgument('module', in_array(self::ALL, $selected_item) ? $modules : $selected_item);
    }

    protected function getModuleModel($name)
    {
        return $name instanceof \Rcv\Modules\Module
            ? $name
            : app('modules')->findOrFail($name);
    }

    private function configureConfirmable(): void
    {
        $this->getDefinition()->addOption(new InputOption(
            name: 'force',
            shortcut: null,
            mode: InputOption::VALUE_NONE,
            description: 'Force the operation to run without confirmation.',
        ));
    }
}
