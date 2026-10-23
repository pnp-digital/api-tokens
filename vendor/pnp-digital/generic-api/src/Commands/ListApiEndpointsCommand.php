<?php

namespace Pipeandpiper\API\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Pipeandpiper\API\Enums\AllowedMethodEnum;
use Pipeandpiper\API\Interfaces\ApiFilterInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Helper\Table as ConsoleTable;
use Illuminate\Database\Eloquent\Model;

class ListApiEndpointsCommand extends Command
{
    /** @var array<string, string> */
    private array $descriptionMappings = [
        "GET" => "Retrieve",
        "POST" => "Add",
        "PUT" => "Update",
        "DELETE" => "Delete",
    ];

    /** @var array<string, string> */
    private array $colMappings = [
        "GET" => "<bg=green;fg=black>   GET   </>",
        "POST" => "<bg=yellow;fg=black>   POST  </>",
        "PUT" => "<bg=yellow;fg=black>   PUT   </>",
        "DELETE" => "<bg=red;fg=white>  DELETE </>",
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:endpoints';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all auto generated API endpoints';

    public function handle(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(app_path('Models'), \FilesystemIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isDir()) {
                /** @var class-string $classAbsolute */
                $classAbsolute = 'App\\Models\\' . $file->getBasename('.php');
                if (class_exists($classAbsolute)) {
                    if (str_contains($classAbsolute, "Abstract")) {
                        continue;
                    }
                    /** @var Model $object */
                    $object = new $classAbsolute;
                    $this->info(Str::pluralStudly(str_replace("_", " ", $object->getTable())) . " endpoints:");
                    $table = new ConsoleTable($this->output);
                    $table->setHeaders([
                        'Method', 'Endpoint', 'Description'
                    ]);
                    $table->setColumnWidths([30, 60]);
                    foreach (["GET", "POST", "PUT", "DELETE"] as $method) {
                        if ($object instanceof ApiFilterInterface) {
                            /** @var list<string> $allowed */
                            $allowed = $classAbsolute::API_ALLOWED_METHODS;
                            if (!empty($allowed)) {
                                if (!in_array(
                                    match ($method) {
                                        "GET" => AllowedMethodEnum::READ,
                                        "POST" => AllowedMethodEnum::CREATE,
                                        "PUT" => AllowedMethodEnum::UPDATE,
                                        "DELETE" => AllowedMethodEnum::DELETE,
                                    },
                                    $allowed
                                )) {
                                    continue;
                                }
                            }
                        }
                        $table->addRow([
                            $this->colMappings[$method],
                            sprintf("<fg=white;bold>/api/v1/%s</>", $object->getTable()),
                            sprintf(
                                "%s basic %s %s", $this->descriptionMappings[$method],
                                Str::singular(str_replace("_", " ", $object->getTable())),
                                $method === "GET" ?
                                    "<fg=white;bold>(no relations included in results)</>" :
                                    "<fg=white;bold>(sub-objects and arrays not allowed)</>"
                            )
                        ]);
                        if ($method === "GET") {
                            $table->addRows([
                                [
                                    $this->colMappings[$method],
                                    sprintf("<fg=white;bold>/api/v1/basic/%s</>", $object->getTable()),
                                    sprintf(
                                        "%s basic %s <fg=white;bold>(no relations included in results)</>",
                                        $this->descriptionMappings[$method],
                                        Str::singular(str_replace("_", " ", $object->getTable()))
                                    )
                                ],
                                [
                                    $this->colMappings[$method],
                                    sprintf("<fg=white;bold>/api/v1/extended/%s</>", $object->getTable()),
                                    sprintf(
                                        "%s extended %s <fg=white;bold>(relations included as sub-objects/arrays)</>",
                                        $this->descriptionMappings[$method],
                                        Str::singular(str_replace("_", " ", $object->getTable()))
                                    )
                                ],
                            ]);
                        }
                    }
                    $table->render();
                }
            }
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(app_path('Enums'), \FilesystemIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isDir()) {
                $classAbsolute = 'App\\Enums\\' . $file->getBasename('.php');
                if (class_exists($classAbsolute)) {
                    /** @var string $t */
                    $t = preg_replace("/Enum\.php$/", "", $file->getBasename());
                    $t = Str::plural(Str::snake($t));
                    /** @var string $t */
                    $t = preg_replace("/^_/", "", $t);
                    $this->info(Str::pluralStudly(str_replace("_", " ", $t)) . " endpoints:");
                    $table = new ConsoleTable($this->output);
                    $table->setHeaders([
                        'Method', 'Endpoint', 'Description'
                    ]);
                    $table->setColumnWidths([30, 60]);
                    $table->addRow([
                        $this->colMappings["GET"],
                        sprintf("<fg=white;bold>/api/v1/basic/%s</>", $t),
                        sprintf(
                            "%s basic %s <fg=white;bold>(no relations included in results)</>",
                            $this->descriptionMappings["GET"],
                            Str::singular(str_replace("_", " ", $t))
                        )
                    ]);
                    $table->render();
                }
            }
        }

        $this->info("Authentication endpoints:");
        $table = new ConsoleTable($this->output);
        $table->setHeaders([
            'Method', 'Endpoint', 'Description'
        ]);
        $table->setColumnWidths([30, 60]);
        $table->addRows([
            [
                $this->colMappings["POST"],
                "<fg=white;bold>/api/v1/login</>",
                "Log in <fg=white;bold>(authentication grants a sanctum bearer token)</>",
            ],
            [
                $this->colMappings["POST"],
                "<fg=white;bold>/api/v1/logout</>",
                "Log out",
            ],
            [
                $this->colMappings["POST"],
                "<fg=white;bold>/api/v1/register</>",
                "Register a new user <fg=white;bold>(can be blocked by a transformer)</>",
            ],
            [
                $this->colMappings["POST"],
                "<fg=white;bold>/api/v1/resetpassword</>",
                "Send password reset email <fg=white;bold>(can be blocked by a transformer)</>",
            ],
        ]);
        $table->render();
    }
}