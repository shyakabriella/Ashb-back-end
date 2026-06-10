<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTaskLoadingIndexes extends Migration
{
    /**
     * Add indexes used by the live task listing page.
     */
    public function up(): void
    {
        if (Schema::hasTable('tasks')) {
            $hasRangeIndex = $this->hasIndex('tasks', 'tasks_end_start_index');
            $hasStatusRangeIndex = $this->hasIndex('tasks', 'tasks_status_end_start_index');
            $hasPropertyRangeIndex = $this->hasIndex('tasks', 'tasks_property_end_start_index');

            Schema::table('tasks', function (Blueprint $table) use (
                $hasRangeIndex,
                $hasStatusRangeIndex,
                $hasPropertyRangeIndex
            ): void {
                if (!$hasRangeIndex) {
                    $table->index(
                        ['end_at', 'start_at'],
                        'tasks_end_start_index'
                    );
                }

                if (
                    !$hasStatusRangeIndex &&
                    Schema::hasColumn('tasks', 'status')
                ) {
                    $table->index(
                        ['status', 'end_at', 'start_at'],
                        'tasks_status_end_start_index'
                    );
                }

                if (
                    !$hasPropertyRangeIndex &&
                    Schema::hasColumn('tasks', 'property_id')
                ) {
                    $table->index(
                        ['property_id', 'end_at', 'start_at'],
                        'tasks_property_end_start_index'
                    );
                }
            });
        }

        // The project may use one of these names for its task-worker pivot table.
        $this->addTaskWorkerIndexes('task_user');
        $this->addTaskWorkerIndexes('task_worker');
        $this->addTaskWorkerIndexes('task_workers');

        if (
            Schema::hasTable('task_updates') &&
            Schema::hasColumn('task_updates', 'task_id')
        ) {
            $indexName = 'task_updates_task_id_index';

            if (!$this->hasIndex('task_updates', $indexName)) {
                Schema::table('task_updates', function (Blueprint $table) use ($indexName): void {
                    $table->index('task_id', $indexName);
                });
            }
        }

        if (
            Schema::hasTable('task_rewards') &&
            Schema::hasColumn('task_rewards', 'task_id')
        ) {
            $hasTaskIndex = $this->hasIndex(
                'task_rewards',
                'task_rewards_task_id_index'
            );

            $hasLatestIndex = $this->hasIndex(
                'task_rewards',
                'task_rewards_task_created_index'
            );

            Schema::table('task_rewards', function (Blueprint $table) use (
                $hasTaskIndex,
                $hasLatestIndex
            ): void {
                if (!$hasTaskIndex) {
                    $table->index(
                        'task_id',
                        'task_rewards_task_id_index'
                    );
                }

                if (
                    !$hasLatestIndex &&
                    Schema::hasColumn('task_rewards', 'created_at')
                ) {
                    $table->index(
                        ['task_id', 'created_at'],
                        'task_rewards_task_created_index'
                    );
                }
            });
        }
    }

    /**
     * Remove indexes created by this migration.
     */
    public function down(): void
    {
        $this->dropIndexIfExists('tasks', 'tasks_end_start_index');
        $this->dropIndexIfExists('tasks', 'tasks_status_end_start_index');
        $this->dropIndexIfExists('tasks', 'tasks_property_end_start_index');

        foreach (['task_user', 'task_worker', 'task_workers'] as $tableName) {
            $this->dropIndexIfExists(
                $tableName,
                $tableName . '_user_task_index'
            );

            $this->dropIndexIfExists(
                $tableName,
                $tableName . '_task_user_index'
            );
        }

        $this->dropIndexIfExists(
            'task_updates',
            'task_updates_task_id_index'
        );

        $this->dropIndexIfExists(
            'task_rewards',
            'task_rewards_task_id_index'
        );

        $this->dropIndexIfExists(
            'task_rewards',
            'task_rewards_task_created_index'
        );
    }

    /**
     * Add indexes to a possible task-worker pivot table.
     */
    private function addTaskWorkerIndexes(string $tableName): void
    {
        if (!Schema::hasTable($tableName)) {
            return;
        }

        if (
            !Schema::hasColumn($tableName, 'task_id') ||
            !Schema::hasColumn($tableName, 'user_id')
        ) {
            return;
        }

        $userTaskIndex = $tableName . '_user_task_index';
        $taskUserIndex = $tableName . '_task_user_index';

        $hasUserTaskIndex = $this->hasIndex($tableName, $userTaskIndex);
        $hasTaskUserIndex = $this->hasIndex($tableName, $taskUserIndex);

        Schema::table($tableName, function (Blueprint $table) use (
            $hasUserTaskIndex,
            $hasTaskUserIndex,
            $userTaskIndex,
            $taskUserIndex
        ): void {
            if (!$hasUserTaskIndex) {
                $table->index(
                    ['user_id', 'task_id'],
                    $userTaskIndex
                );
            }

            if (!$hasTaskUserIndex) {
                $table->index(
                    ['task_id', 'user_id'],
                    $taskUserIndex
                );
            }
        });
    }

    /**
     * Check whether an index exists.
     */
    private function hasIndex(string $tableName, string $indexName): bool
    {
        if (!Schema::hasTable($tableName)) {
            return false;
        }

        try {
            return collect(Schema::getIndexes($tableName))->contains(
                static fn (array $index): bool =>
                    ($index['name'] ?? null) === $indexName
            );
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Drop an index only if it exists.
     */
    private function dropIndexIfExists(
        string $tableName,
        string $indexName
    ): void {
        if (!$this->hasIndex($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
            $table->dropIndex($indexName);
        });
    }
}