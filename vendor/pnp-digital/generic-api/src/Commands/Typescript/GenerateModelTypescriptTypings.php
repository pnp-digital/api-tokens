<?php

namespace Pipeandpiper\API\Commands\Typescript;

use Illuminate\Console\Command;
use Illuminate\Console\Concerns\InteractsWithIO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Pipeandpiper\API\Collectors\ModelCollector;
use Pipeandpiper\API\Transformers\ModelTypescriptTransformer;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Spatie\LaravelTypeScriptTransformer\Commands\TypeScriptTransformCommand;
use Spatie\LaravelTypeScriptTransformer\Transformers\SpatieStateTransformer;
use Spatie\TypeScriptTransformer\Collectors\DefaultCollector;
use Spatie\TypeScriptTransformer\Collectors\EnumCollector;
use Spatie\TypeScriptTransformer\Transformers\DtoTransformer;
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfig;
use SplFileInfo;
use Illuminate\Filesystem\Filesystem;

class GenerateModelTypescriptTypings extends Command
{
    use InteractsWithIO;

    /**
     * This default path is within the resources directory
     *
     * @var string
     */
    protected const DEFAULT_OUTPUT_PATH = '/types/generated.d.ts';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:definitions
                            {--force : Force the operation to run when in production}
                            {--path= : Specify a path with classes to transform}
                            {--output= : Use another file to output}
                            {--format : Use Prettier to format the output}';

    /**
     * Explicit class names to add
     *
     * @var list<class-string>
     */
    protected array $modelClassNames = [];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate typescript types based on eloquent models';

    /**
     * Handle command
     * @return int return status
     */
    public function handle(): int
    {
        $fs = new Filesystem();
        $temporaryFolderPath = storage_path() . '/Generated';

        // ensure no temporary class folder exists
        $fs->deleteDirectory($temporaryFolderPath);

        // fetch class names
        $this->getFullyQualifiedClassNames();

        $fs->makeDirectory($temporaryFolderPath);

        foreach ($this->modelClassNames as $class) {
            if (class_exists($class) && !str_contains($class, "Abstract")) {
                $model = new $class();

                if ($model instanceof Model) {
                    $modelName = substr($class, strrpos($class, '\\') + 1);
                    $table = $model->getTable();

                    if (Schema::hasTable($table)) {
                        /** @var list<object{
                         *     Field: string,
                         *     Type: string,
                         *     Null: string,
                         *     Key: string,
                         *     Default: mixed,
                         *     Extra: string
                         * }> $columns
                         */
                        $columns = DB::select("SHOW COLUMNS FROM $table");

                        /** @var list<string> $properties */
                        $properties = array_map(
                            function ($column) {
                                $type = $this->mapDatabaseTypeToPhpType($column);
                                $nullableTypeOperator = $column->Null === 'YES' ? '?' : '';

                                return "public $nullableTypeOperator" . "$type  $$column->Field; " . PHP_EOL;
                            },
                            $columns
                        );

                        $classAsString = $this->getClassAsString(
                            $properties,
                            $modelName,
                            $temporaryFolderPath
                        );

                        file_put_contents($temporaryFolderPath . "/$modelName.php", $classAsString);
                    }
                }
            }
        }

        $savePath = resource_path() . GenerateModelTypescriptTypings::DEFAULT_OUTPUT_PATH;
        $returnValue = $this->runTypescriptGenerationCommand($temporaryFolderPath, $savePath);

        $fs->deleteDirectory($temporaryFolderPath);

        $this->newLine();
        $this->info("Typescript API Definitions");
        $this->newLine();
        /** @var string $contents */
        $contents = file_get_contents($savePath);
        $this->line($contents);
        $this->info("Saved definitions to " . $savePath);

        return $returnValue;
    }


    /**
     * @param object{Type: string} $column
     */
    private function mapDatabaseTypeToPhpType($column): string
    {
        $types = [
            'double' => 'float',
            'numeric' => 'float',
            'decimal' => 'float',
            'varchar' => 'string',
            'char' => 'string',
            'binary' => 'string',
            'varbinary' => 'string',
            'tinyblob' => 'string',
            'blob' => 'string',
            'mediumblob' => 'string',
            'longblob' => 'string',
            'tinytext' => 'string',
            'text' => 'string',
            'mediumtext' => 'string',
            'longtext' => 'string',
            'enum' => 'string',
            'bigint' => 'int',
            'smallint' => 'int',
            'int' => 'int',
            'tinyint' => 'bool',
            'id' => 'int',
            'date' => 'string',
            'time' => 'string',
            'datetime' => 'string',
            'year' => 'string',
            'timestamp' => 'string',
            'json' => 'string'
        ];

        $type = explode(' ', (string)preg_replace('/\s*\([^)]*\)/', '', $column->Type))[0];

        return $types[$type];
    }

    /**
     * @param list<string> $properties
     */
    private function getClassAsString(array $properties, string $className, string $temporaryFolderPath): string
    {
        $break = PHP_EOL;
        $propertiesToString = implode("", $properties);
        $namespace = 'Typescript\\Generated';

        return "<?php $break namespace $namespace; $break /** $break * @javascript $break */$break class $className { $propertiesToString }";
    }

    /**
     * Convert file names in the app models path to fully qualified class names with namespaces
     */
    private function getFullyQualifiedClassNames(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(app_path('Models'), \FilesystemIterator::SKIP_DOTS)
        );
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isDir()) {
                /** @var class-string $className */
                $className = 'App\\Models\\' . $file->getBasename('.php');
                $this->modelClassNames[] = $className;
            }
        }
    }

    /**
     * Call spatie/laravel-typescript-generator with our crafted configuration.
     * This passes our IO signature into the other command, which we instantiate by composition.
     * @param string $inputPath input path to search for PHP classes to convert
     * @param string $outputFile Output .ts file to generate
     * @return int Command return value
     */
    public function runTypescriptGenerationCommand(string $inputPath, string $outputFile): int
    {
        $cmd = new TypeScriptTransformCommand();
        $config = new TypeScriptTransformerConfig();
        $config->autoDiscoverTypes($inputPath);
        $config->outputFile($outputFile);
        $config->collectors([ DefaultCollector::class, EnumCollector::class, ModelCollector::class ]);
        $config->transformers([
            ModelTypescriptTransformer::class,
            SpatieStateTransformer::class,
            EnumTransformer::class,
            DtoTransformer::class,
        ]);
        $cmd->setLaravel($this->getLaravel());
        $this->input->setOption("path", storage_path() . '/Generated');
        $cmd->setDefinition($this->getDefinition());
        $cmd->setInput($this->input);
        $cmd->setOutput($this->getOutput());
        return $cmd->handle($config);
    }
}
