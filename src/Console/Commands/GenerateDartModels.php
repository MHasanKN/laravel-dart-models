<?php

namespace Mhasankn\DartModels\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GenerateDartModels extends Command
{
    protected $signature = 'dart:models
                            {--from-migrations : Generate models from migrations}
                            {--from-database : Generate models from the database schema}';

    protected $description = 'Generate Flutter models from Laravel migrations';

    protected $schema = []; // Schema representation

    /**
     * Handles the generation of Dart models based on options provided.
     * It validates whether the models should be generated from migrations or the database schema.
     *
     * @return void
     */
    public function handle(): void
    {
        $fromMigrations = $this->option('from-migrations');
        $fromDatabase = $this->option('from-database');

        if ($fromMigrations && $fromDatabase) {
            $this->error('Please specify only one option: --from-migrations or --from-database.');
            return;
        }

        if ($fromDatabase) {
            $this->generateFromDatabase();
        } else {
            // Default to migrations if no option is provided
            $this->generateFromMigrations();
        }


    }

    /**
     * Generates Dart models from Laravel migration files.
     * It scans the migrations folder, processes each migration, and generates models accordingly.
     *
     * @return void
     */
    protected function generateFromMigrations(): void
    {
        $this->info('Generating models from migrations...');

        $migrationPath = database_path('migrations');
        $migrationFiles = glob($migrationPath . '/*.php');

        // Sort migration files by their filename (which includes timestamp)
        sort($migrationFiles);

        // Process each migration file
        foreach ($migrationFiles as $file) {
            $this->processMigration($file);
        }

        // Generate models from the final schema
        foreach ($this->schema as $tableName => $columns) {
            $this->generateDartModel($tableName, $columns);
        }

        $this->info('Flutter models generated successfully.');
    }

    /**
     * Generates Dart models based on the current database schema.
     * It queries the database for tables and columns, generating corresponding models.
     *
     * @return void
     */
    protected function generateFromDatabase(): void
    {
        $this->info('Generating models from the database schema...');

        $tables = $this->getDatabaseTables();

        foreach ($tables as $tableName) {
            $columns = $this->getTableColumns($tableName);
            if ($columns) {
                $this->generateDartModel($tableName, $columns);
            }
        }

        $this->info('Models generated from the database successfully.');
    }

    /**
     * Retrieves a list of all database tables using Laravel's Schema facade.
     *
     * @return array List of table names in the database.
     */
    protected function getDatabaseTables(): array
    {
        try {
            // Use Schema facade to list all tables.
            return Schema::getTableListing();
        } catch (\Exception $e) {
            $this->error("Error retrieving tables: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Retrieves column details for a specific table.
     * It maps the column type and nullable state for each column.
     *
     * @param string $tableName The name of the table.
     * @return array List of columns with their type and nullable state.
     */
    protected function getTableColumns(string $tableName): array
    {
        try {
            // Get all columns for the table using Schema facade
            $columns = Schema::getColumnListing($tableName);

            return array_map(function ($column) use ($tableName) {
                $tempType = Schema::getColumnType($tableName, $column);
                $isNullable = !Schema::hasColumn($tableName, $column);
                $type = $this->convertDoctrineTypeToLaravel($tempType);


                return [
                    'name' => $column,
                    'type' => $type,
                    'nullable' => $isNullable,
                ];
            }, $columns);
        } catch (\Exception $e) {
            $this->error("Error retrieving columns for table '$tableName': " . $e->getMessage());
            return [];
        }
    }

    /**
     * Converts a doctrine column type to a Laravel-compatible type.
     *
     * @param string $doctrineType The column type from Doctrine.
     * @return string The mapped Laravel-compatible type.
     */
    protected function convertDoctrineTypeToLaravel(string $doctrineType): string
    {
        $typeMapping = [
            // String-based types
            'string' => 'string',
            'text' => 'text',
            'tinytext' => 'tinyText',
            'mediumtext' => 'mediumText',
            'longtext' => 'longText',
            'char' => 'char',
            'varchar' => 'string',
            'uuid' => 'uuid',
            'ulid' => 'ulid',

            // Numeric types
            'integer' => 'integer',
            'bigint' => 'bigInteger',
            'smallint' => 'smallInteger',
            'tinyint' => 'tinyInteger',
            'mediumint' => 'mediumInteger',
            'numeric' => 'decimal',
            'decimal' => 'decimal',
            'float' => 'float',
            'double' => 'double',
            'real' => 'double',
            'unsignedinteger' => 'unsignedInteger',
            'unsignedbigint' => 'unsignedBigInteger',

            // Date/Time types
            'date' => 'date',
            'datetime' => 'datetime',
            'datetimetz' => 'datetime',
            'timestamp' => 'timestamp',
            'timestamptz' => 'timestampTz',
            'time' => 'time',
            'timetz' => 'timeTz',
            'year' => 'year',

            // Boolean
            'boolean' => 'boolean',
            'bit' => 'boolean',

            // JSON and Binary types
            'json' => 'json',
            'jsonb' => 'jsonb',
            'binary' => 'binary',
            'blob' => 'binary',
            'varbinary' => 'binary',

            // Spatial types (used in MySQL/PostGIS)
            'geometry' => 'geometry',
            'point' => 'point',
            'linestring' => 'lineString',
            'polygon' => 'polygon',
            'multipoint' => 'multiPoint',
            'multilinestring' => 'multiLineString',
            'multipolygon' => 'multiPolygon',
            'geometrycollection' => 'geometryCollection',

            // Special types
            'ipaddress' => 'ipAddress',
            'macaddress' => 'macAddress',
            'enum' => 'enum',
            'set' => 'set',
        ];

        return $typeMapping[strtolower($doctrineType)] ?? 'string';
    }

    /**
     * Processes a migration file to extract table operations (create, modify, drop).
     *
     * @param string $file The path to the migration file.
     * @return void
     */
    protected function processMigration(string $file): void
    {
        $content = file_get_contents($file);

        // Remove comments and normalize whitespace
        $content = preg_replace('/\/\/.*$/m', '', $content);
        $content = preg_replace('/\s+/', ' ', $content);

        // Updated regex pattern
        $pattern = '/Schema::(create|table|dropIfExists)\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,/';

        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $operation = $match[1];
                $tableName = $match[2];

                // Extract closure content associated with this operation
                $closurePattern = '/Schema::' . $operation . '\s*\(\s*[\'"]' . preg_quote($tableName, '/') . '[\'"]\s*,\s*function\s*\([^\)]*\)\s*\{(.*?)\}\s*\);/s';

                if (preg_match($closurePattern, $content, $closureMatches)) {
                    $closureContent = $closureMatches[1];

                    if ($operation === 'create') {
                        // Extract columns for the new table
                        $columns = $this->extractColumns($closureContent);
                        $this->schema[$tableName] = $columns;
                    } elseif ($operation === 'table') {
                        // Modify existing table
                        if (!isset($this->schema[$tableName])) {
                            $this->schema[$tableName] = [];
                        }
                        $this->modifyTable($closureContent, $tableName);
                    }
                } elseif ($operation === 'dropIfExists') {
                    // Remove table from schema
                    unset($this->schema[$tableName]);
                }
            }
        }
    }

    /**
     * Extracts columns from the closure content of a migration.
     *
     * @param string $closureContent The content inside the migration's closure.
     * @return array List of columns with their type and nullable state.
     */
    protected function extractColumns(string $closureContent): array
    {
        $columns = [];

        // Split the closure content into lines
        $lines = explode(';', $closureContent);

        foreach ($lines as $line) {
            // Remove comments and trim whitespace
            $line = preg_replace('/\/\/.*$/', '', $line);  // Remove comments
            $line = trim($line);  // Trim whitespace

            // Skip empty lines
            if (empty($line)) {
                continue;
            }

            // Match standard column definitions
            if (preg_match('/\$table->([a-zA-Z_]+)\(\s*[\'"]([^\'"]+)[\'"](?:\s*,\s*\d+)?\s*\)(.*)/', $line, $colMatches)) {
                $type = trim($colMatches[1]);  // Column type
                $name = trim($colMatches[2]);  // Column name
                $attributes = $colMatches[3];  // Column attributes

                // Check if nullable
                $isNullable = strpos($attributes, '->nullable()') !== false;

                // Store the column details
                $columns[$name] = [
                    'type' => $type,
                    'name' => $name,
                    'nullable' => $isNullable,
                ];
            }
        }

        return $columns;
    }

    /**
     * Modifies the schema of an existing table by adding, renaming, or dropping columns.
     *
     * @param string $tableName The name of the table to modify.
     * @param string $content The content of the migration that modifies the table.
     * @return void
     */
    protected function modifyTable(string $tableName, string $content): void
    {
        // Build a pattern to match the specific Schema::table operation for the given table
        $pattern = '/Schema::table\s*\(\s*[\'"]' . preg_quote($tableName, '/') . '[\'"]\s*,\s*function\s*\([^\)]*\)\s*\{(.*?)\}\s*\);/s';

        if (preg_match($pattern, $content, $matches)) {
            // $matches[1] contains the contents inside the closure (i.e., the table modifications)
            $closureContent = $matches[1];

            // Split the closure content into lines
            $lines = explode(';', $closureContent);

            foreach ($lines as $line) {
                // Remove comments and trim whitespace
                $line = preg_replace('/\/\/.*$/', '', $line);
                $line = trim($line);

                // Skip empty lines
                if (empty($line)) {
                    continue;
                }

                // Process column additions
                if (preg_match('/\$table->([a-zA-Z_]+)\(\s*[\'"]([^\'"]+)[\'"](?:\s*,\s*\d+)?\s*\)(.*)/', $line, $colMatches)) {
                    $action = $colMatches[1];
                    $name = $colMatches[2];
                    $attributes = $colMatches[3];
                    $isNullable = str_contains($attributes, '->nullable()');

                    if (in_array($action, ['string', 'integer', 'bigInteger', 'text', 'boolean', /* add other types as needed */])) {
                        // Add or modify column
                        $this->schema[$tableName][$name] = [
                            'type' => $action,
                            'name' => $name,
                            'nullable' => $isNullable,
                        ];
                    }
                }

                // Process column drops
                if (preg_match('/\$table->dropColumn\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $line, $dropMatches)) {
                    $name = $dropMatches[1];
                    unset($this->schema[$tableName][$name]);
                }

                // Process column renames
                if (preg_match('/\$table->renameColumn\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)/', $line, $renameMatches)) {
                    $oldName = $renameMatches[1];
                    $newName = $renameMatches[2];
                    if (isset($this->schema[$tableName][$oldName])) {
                        $column = $this->schema[$tableName][$oldName];
                        $column['name'] = $newName;
                        $this->schema[$tableName][$newName] = $column;
                        unset($this->schema[$tableName][$oldName]);
                    }
                }

                // Handle other modifications as needed...
            }
        }
    }

    /**
     * Generates a Dart model class based on the given table schema.
     *
     * @param string $tableName The name of the table.
     * @param array $columns List of columns with their type and nullable state.
     * @return void
     */
    protected function generateDartModel(string $tableName, array $columns): void
    {
        $className = ucfirst(Str::camel(Str::singular($tableName)));
        $dartCode = "class $className {\n";

        // Add properties
        foreach ($columns as $column) {
            $dartType = $this->mapColumnTypeToDart($column['type']);
            $nullableSuffix = $column['nullable'] ? '?' : '';
            $dartCode .= "  final $dartType$nullableSuffix {$column['name']};\n";
        }

        // Add constructor
        $dartCode .= "\n  $className({\n";
        foreach ($columns as $column) {
            if ($column['nullable']) {
                $dartCode .= "    this.{$column['name']},\n";
            } else {
                $dartCode .= "    required this.{$column['name']},\n";
            }
        }
        $dartCode .= "  });\n\n";

        // Add fromJson factory method
        $dartCode .= "  factory $className.fromJson(Map<String, dynamic> json) {\n";
        $dartCode .= "    return $className(\n";
        foreach ($columns as $column) {
            $name = $column['name'];
            $dartType = $this->mapColumnTypeToDart($column['type']);
            $isNullable = $column['nullable'];
            if ($dartType == 'DateTime') {
                if ($isNullable) {
                    $dartCode .= "      $name: json['$name'] != null ? DateTime.parse(json['$name']) : null,\n";
                } else {
                    $dartCode .= "      $name: DateTime.parse(json['$name']),\n";
                }
            } else {
                $cast = $this->getJsonCast($dartType);
                if ($isNullable) {
                    $dartCode .= "      $name: json['$name'] != null ? json['$name']$cast : null,\n";
                } else {
                    $dartCode .= "      $name: json['$name']$cast,\n";
                }
            }
        }
        $dartCode .= "    );\n";
        $dartCode .= "  }\n\n";

        // Add toJson method
        $dartCode .= "  Map<String, dynamic> toJson() {\n";
        $dartCode .= "    return {\n";
        foreach ($columns as $column) {
            $name = $column['name'];
            $dartType = $this->mapColumnTypeToDart($column['type']);
            $isNullable = $column['nullable'];
            if ($dartType == 'DateTime') {
                if ($isNullable) {
                    $dartCode .= "      '$name': $name?.toIso8601String(),\n";
                } else {
                    $dartCode .= "      '$name': $name.toIso8601String(),\n";
                }
            } else {
                $dartCode .= "      '$name': $name,\n";
            }
        }
        $dartCode .= "    };\n";
        $dartCode .= "  }\n";

        // Close class
        $dartCode .= "}\n";

        // Ensure the output directory exists
        $outputDir = base_path('dart_models');
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // Save to file
        $outputPath = $outputDir . "/$className.dart";
        file_put_contents($outputPath, $dartCode);

        $this->info("Generated model for table '$tableName' as '$className.dart'.");
    }

    /**
     * Maps Laravel's column types to their Dart equivalents.
     *
     * This function provides a mapping between Laravel's database column types and
     * the appropriate Dart data types used for Flutter models. If a type is not
     * found in the mapping, it defaults to 'dynamic'.
     *
     * @param string $type The column type from Laravel.
     * @return string The corresponding Dart type.
     */
    protected function mapColumnTypeToDart(string $type): string
    {
        $typeMapping = [
            // String-based types
            'string' => 'String',
            'text' => 'String',
            'longText' => 'String',
            'mediumText' => 'String',
            'tinyText' => 'String',
            'char' => 'String',
            'uuid' => 'String',
            'ulid' => 'String',
            'json' => 'Map<String, dynamic>',
            'jsonb' => 'Map<String, dynamic>',
            'enum' => 'String',
            'set' => 'List<String>',
            'ipAddress' => 'String',
            'macAddress' => 'String',

            // Integer-based types
            'integer' => 'int',
            'tinyInteger' => 'int',
            'smallInteger' => 'int',
            'mediumInteger' => 'int',
            'bigInteger' => 'int',
            'increments' => 'int',
            'tinyIncrements' => 'int',
            'smallIncrements' => 'int',
            'mediumIncrements' => 'int',
            'bigIncrements' => 'int',
            'unsignedInteger' => 'int',
            'unsignedTinyInteger' => 'int',
            'unsignedSmallInteger' => 'int',
            'unsignedMediumInteger' => 'int',
            'unsignedBigInteger' => 'int',
            'foreignId' => 'int',

            // Floating-point types
            'float' => 'double',
            'double' => 'double',
            'decimal' => 'double',
            'unsignedDecimal' => 'double',

            // Boolean
            'boolean' => 'bool',

            // Date/Time types
            'date' => 'DateTime',
            'datetime' => 'DateTime',
            'timestamp' => 'DateTime',
            'dateTimeTz' => 'DateTime',
            'timestampTz' => 'DateTime',
            'time' => 'String',  // No specific time type in Dart
            'timeTz' => 'String',
            'year' => 'int',

            // Binary and Geography data
            'binary' => 'List<int>',
            'geometry' => 'String',
            'geography' => 'String',

            // Polymorphic and foreign keys
            'morphs' => 'Map<String, dynamic>',
            'nullableMorphs' => 'Map<String, dynamic>',
            'ulidMorphs' => 'Map<String, dynamic>',
            'nullableUlidMorphs' => 'Map<String, dynamic>',
            'uuidMorphs' => 'Map<String, dynamic>',
            'nullableUuidMorphs' => 'Map<String, dynamic>',
            'foreignUuid' => 'String',
            'foreignUlid' => 'String',
            'foreignIdFor' => 'int',

            // Soft Deletes and Timestamps
            'softDeletes' => 'DateTime?',
            'softDeletesTz' => 'DateTime?',
            'timestamps' => 'Map<String, DateTime?>',
            'timestampsTz' => 'Map<String, DateTime?>',
            'nullableTimestamps' => 'Map<String, DateTime?>',

            // Miscellaneous
            'id' => 'int',
            'rememberToken' => 'String',
        ];

        return $typeMapping[$type] ?? 'dynamic';
    }

    /**
     * Provides the appropriate Dart cast expression based on the Dart type.
     *
     * This function returns a cast expression to convert JSON values into their
     * Dart equivalents (e.g., 'as int' for integers). If no cast is needed, it returns an empty string.
     *
     * @param string $dartType The Dart type for which the cast expression is required.
     * @return string The corresponding cast expression or an empty string.
     */
    protected function getJsonCast(string $dartType): string
    {
        return match ($dartType) {
            'int' => ' as int',
            'double' => ' as double',
            'bool' => ' as bool',
            default => '',
        };
    }
}
