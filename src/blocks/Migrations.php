<?php

namespace rjapi\blocks;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use rjapi\helpers\Classes;
use rjapi\helpers\Console;
use rjapi\RJApiGenerator;

class Migrations extends MigrationsAbstract
{
    use ContentManager, MigrationsTrait, EntitiesTrait;
    /** @var RJApiGenerator $generator */
    protected $generator  = null;
    protected $sourceCode = '';

    public function __construct($generator)
    {
        $this->generator = $generator;
    }

    public function setCodeState($generator)
    {
        $this->generator = $generator;
    }

    public function create()
    {
        $table = '';
        $this->setTag();

        $this->setUse(Schema::class);
        $this->setUse(Blueprint::class);
        $migrationClass = Migration::class;
        $this->setUse($migrationClass, false, true);
        // migrate up
        $this->startClass(
            ucfirst(ModelsInterface::MIGRATION_CREATE) .
            str_replace(
                [
                    PhpEntitiesInterface::DASH,
                    PhpEntitiesInterface::UNDERSCORE
                ], '', ucwords(
                    $this->generator->objectName, PhpEntitiesInterface::DASH . PhpEntitiesInterface::UNDERSCORE
                )
            )
            . ucfirst(ModelsInterface::MIGRATION_TABLE), Classes::getName($migrationClass)
        );
        $this->startMethod(ModelsInterface::MIGRATION_METHOD_UP, PhpEntitiesInterface::PHP_MODIFIER_PUBLIC);
        // make entity lc + underscore
        $words = preg_split(self::PATTERN_SPLIT_UC, lcfirst($this->generator->objectName));
        foreach($words as $key => $word)
        {
            $table .= $word;
            if(empty($words[$key + 1]) === false)
            {
                $table .= PhpEntitiesInterface::UNDERSCORE;
            }
        }
        $table = strtolower($table);
        $this->openSchema($table);
        $this->setRows();
        $this->closeSchema();
        $this->endMethod();
        // migrate down
        $this->startMethod(ModelsInterface::MIGRATION_METHOD_DOWN, PhpEntitiesInterface::PHP_MODIFIER_PUBLIC);
        $this->createSchema(ModelsInterface::MIGRATION_METHOD_DROP, $table);
        $this->endMethod();
        $this->endClass();

        $migrationMask = date(self::PATTERN_TIME, time()) . mt_rand(10, 99);

        $migrationName = ModelsInterface::MIGRATION_CREATE . PhpEntitiesInterface::UNDERSCORE .
                         strtolower($this->generator->objectName) .
                         PhpEntitiesInterface::UNDERSCORE . ModelsInterface::MIGRATION_TABLE;
        if(FileManager::migrationNotExists($this->generator, $migrationName))
        {
            $file = $this->generator->formatMigrationsPath() . $migrationMask . PhpEntitiesInterface::UNDERSCORE .
                    $migrationName . PhpEntitiesInterface::PHP_EXT;
            // if migration file with the same name ocasionally exists we do not override it
            $isCreated = FileManager::createFile($file, $this->sourceCode);
            if($isCreated)
            {
                Console::out($file . PhpEntitiesInterface::SPACE . Console::CREATED, Console::COLOR_GREEN);
            }
        }
    }

    /**
     *  Creates pivot table for ManyToMany relations if needed
     */
    public function createPivot()
    {
        $middlewareEntity = $this->getMiddleware($this->generator->version, $this->generator->objectName);
        $middleWare       = new $middlewareEntity();

        if(method_exists($middleWare, ModelsInterface::MODEL_METHOD_RELATIONS))
        {
            $relations = $middleWare->relations();

            foreach($relations as $relationEntity)
            {
                $entityFile = $this->generator->formatEntitiesPath()
                              . PhpEntitiesInterface::SLASH .
                              $this->generator->objectName .
                              ucfirst($relationEntity) .
                              PhpEntitiesInterface::PHP_EXT;

                if(file_exists($entityFile))
                {
                    $table        = '';
                    $relatedTable = '';
                    $this->setTag();

                    $this->setUse(Schema::class);
                    $this->setUse(Blueprint::class);
                    $migrationClass = Migration::class;
                    $this->setUse($migrationClass, false, true);
                    // migrate up
                    $this->startClass(
                        ucfirst(ModelsInterface::MIGRATION_CREATE) .
                        str_replace(
                            [
                                PhpEntitiesInterface::DASH,
                                PhpEntitiesInterface::UNDERSCORE
                            ], '', ucwords(
                                $this->generator->objectName, PhpEntitiesInterface::DASH .
                                                              PhpEntitiesInterface::UNDERSCORE
                            )
                        ) .
                        str_replace(
                            [
                                PhpEntitiesInterface::DASH,
                                PhpEntitiesInterface::UNDERSCORE
                            ], '', ucwords(
                                $relationEntity, PhpEntitiesInterface::DASH .
                                                 PhpEntitiesInterface::UNDERSCORE
                            )
                        ) .
                        ucfirst(ModelsInterface::MIGRATION_TABLE), Classes::getName($migrationClass)
                    );
                    $this->startMethod(ModelsInterface::MIGRATION_METHOD_UP, PhpEntitiesInterface::PHP_MODIFIER_PUBLIC);
                    // make first entity lc + underscore
                    $words = preg_split(self::PATTERN_SPLIT_UC, lcfirst($this->generator->objectName));
                    foreach($words as $key => $word)
                    {
                        $table .= $word;
                        if(empty($words[$key + 1]) === false)
                        {
                            $table .= PhpEntitiesInterface::UNDERSCORE;
                        }
                    }
                    // make 2nd entity lc + underscore
                    $words = preg_split(self::PATTERN_SPLIT_UC, lcfirst($relationEntity));
                    foreach($words as $key => $word)
                    {
                        $relatedTable .= $word;
                        if(empty($words[$key + 1]) === false)
                        {
                            $relatedTable .= PhpEntitiesInterface::UNDERSCORE;
                        }
                    }
                    $table          = strtolower($table);
                    $relatedTable   = strtolower($relatedTable);
                    $combinedTables = $table . PhpEntitiesInterface::UNDERSCORE . $relatedTable;

                    $this->openSchema($combinedTables);
                    $this->setPivotRows($relationEntity);
                    $this->closeSchema();
                    $this->endMethod();
                    // migrate down
                    $this->startMethod(ModelsInterface::MIGRATION_METHOD_DOWN, PhpEntitiesInterface::PHP_MODIFIER_PUBLIC);
                    $this->createSchema(ModelsInterface::MIGRATION_METHOD_DROP, $combinedTables);
                    $this->endMethod();
                    $this->endClass();

                    $migrationMask = date(self::PATTERN_TIME, time()) . mt_rand(10, 99);

                    $migrationName = ModelsInterface::MIGRATION_CREATE . PhpEntitiesInterface::UNDERSCORE
                                     . strtolower($this->generator->objectName)
                                     . PhpEntitiesInterface::UNDERSCORE . $relationEntity .
                                     PhpEntitiesInterface::UNDERSCORE . ModelsInterface::MIGRATION_TABLE;

                    if(FileManager::migrationNotExists($this->generator, $migrationName))
                    {
                        $file = $this->generator->formatMigrationsPath() . $migrationMask
                                . PhpEntitiesInterface::UNDERSCORE . $migrationName . PhpEntitiesInterface::PHP_EXT;
                        // if migration file with the same name ocasionally exists we do not override it
                        $isCreated = FileManager::createFile($file, $this->sourceCode);
                        if($isCreated)
                        {
                            Console::out($file . PhpEntitiesInterface::SPACE . Console::CREATED, Console::COLOR_GREEN);
                        }
                    }
                }
            }
        }
    }
}