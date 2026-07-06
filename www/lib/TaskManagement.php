<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/UserContext.php';
require_once __DIR__ . '/ActivityLog.php';

class TaskManagement {
    private static function pdo(): PDO {
        return pdo();
    }

    private static function log(string $action, ?int $taskId, array $details = []): void {
        try {
            $ctx = UserContext::getLoggedInUserContext();
            $meta = $details;
            if ($taskId !== null && !array_key_exists('task_id', $meta)) {
                $meta['task_id'] = $taskId;
            }
            ActivityLog::log($ctx, $action, $meta);
        } catch (\Throwable $e) {
            // Best-effort logging
        }
    }

    /**
     * Adjust a date for a given month to handle edge cases
     * - Feb 29→28 for annual tasks
     * - Day 31→last day of month if month has fewer than 31 days
     * - Day 29-30→28 in February
     */
    private static function adjustDateForMonth(int $month, int $day, int $year): string {
        // Get the last day of the specified month
        $lastDayOfMonth = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
        
        // If the requested day is greater than the last day of the month, use the last day
        if ($day > $lastDayOfMonth) {
            $day = $lastDayOfMonth;
        }
        
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * Calculate the next due date for an annual task
     */
    private static function calculateAnnualDueDate(string $annualDate): ?string {
        if (!$annualDate || !preg_match('/^(\d{2})-(\d{2})$/', $annualDate, $m)) {
            return null;
        }
        
        $month = (int)$m[1];
        $day = (int)$m[2];
        $currentYear = (int)date('Y');
        
        // Try this year first
        $thisYearDate = self::adjustDateForMonth($month, $day, $currentYear);
        if ($thisYearDate >= date('Y-m-d')) {
            return $thisYearDate;
        }
        
        // Otherwise next year
        return self::adjustDateForMonth($month, $day, $currentYear + 1);
    }

    /**
     * Calculate the next due date for a monthly task
     */
    private static function calculateMonthlyDueDate(int $dayOfMonth): string {
        $currentYear = (int)date('Y');
        $currentMonth = (int)date('m');
        $today = date('Y-m-d');
        
        // Try this month first
        $thisMonthDate = self::adjustDateForMonth($currentMonth, $dayOfMonth, $currentYear);
        if ($thisMonthDate >= $today) {
            return $thisMonthDate;
        }
        
        // Otherwise next month
        $nextMonth = $currentMonth + 1;
        $nextYear = $currentYear;
        if ($nextMonth > 12) {
            $nextMonth = 1;
            $nextYear++;
        }
        
        return self::adjustDateForMonth($nextMonth, $dayOfMonth, $nextYear);
    }

    /**
     * Get the most recent completion for a task
     */
    private static function getLatestCompletion(int $taskId): ?array {
        $sql = 'SELECT * FROM task_completions WHERE task_id = ? ORDER BY completed_on DESC LIMIT 1';
        $st = self::pdo()->prepare($sql);
        $st->execute([$taskId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /**
     * Check if there's a completion for a specific period
     */
    private static function hasCompletionFor(int $taskId, string $completedFor): bool {
        $sql = 'SELECT 1 FROM task_completions WHERE task_id = ? AND completed_for = ? LIMIT 1';
        $st = self::pdo()->prepare($sql);
        $st->execute([$taskId, $completedFor]);
        return (bool)$st->fetchColumn();
    }

    /**
     * Calculate task status and days until due
     * Returns: ['status' => string, 'days_until_due' => int|null, 'due_date' => string|null]
     */
    public static function calculateTaskStatus(array $task): array {
        $today = date('Y-m-d');
        $recurrenceType = $task['recurrence_type'];
        
        // If task is marked as completed (no longer needed)
        if (!empty($task['completed'])) {
            return ['status' => 'Completed', 'days_until_due' => null, 'due_date' => null];
        }

        switch ($recurrenceType) {
            case 'one-off':
                $dueDate = $task['date'];
                if (!$dueDate) {
                    return ['status' => 'Not complete', 'days_until_due' => null, 'due_date' => null];
                }
                
                $hasCompletion = self::hasCompletionFor($task['id'], $dueDate);
                $daysUntil = (int)((strtotime($dueDate) - strtotime($today)) / 86400);
                
                if ($hasCompletion) {
                    return ['status' => 'Completed', 'days_until_due' => $daysUntil, 'due_date' => $dueDate];
                } elseif ($daysUntil < 0) {
                    return ['status' => 'Past Due', 'days_until_due' => $daysUntil, 'due_date' => $dueDate];
                } elseif ($daysUntil <= 30) {
                    return ['status' => 'Due soon', 'days_until_due' => $daysUntil, 'due_date' => $dueDate];
                } else {
                    return ['status' => 'Not complete', 'days_until_due' => $daysUntil, 'due_date' => $dueDate];
                }

            case 'annual':
                $dueDate = self::calculateAnnualDueDate($task['annual_date']);
                if (!$dueDate) {
                    return ['status' => 'Not complete', 'days_until_due' => null, 'due_date' => null];
                }
                
                $currentYear = date('Y');
                $hasCompletion = self::hasCompletionFor($task['id'], $currentYear);
                $daysUntil = (int)((strtotime($dueDate) - strtotime($today)) / 86400);
                
                if ($hasCompletion) {
                    return ['status' => 'Current', 'days_until_due' => $daysUntil, 'due_date' => $dueDate];
                } elseif ($daysUntil < 0) {
                    return ['status' => 'Past Due', 'days_until_due' => $daysUntil, 'due_date' => $dueDate];
                } elseif ($daysUntil <= 30) {
                    return ['status' => 'Due soon', 'days_until_due' => $daysUntil, 'due_date' => $dueDate];
                } else {
                    return ['status' => 'Not complete', 'days_until_due' => $daysUntil, 'due_date' => $dueDate];
                }

            case 'monthly':
                if (!$task['day_of_month']) {
                    return ['status' => 'Not complete', 'days_until_due' => null, 'due_date' => null];
                }
                
                $dueDate = self::calculateMonthlyDueDate((int)$task['day_of_month']);
                $currentMonth = date('Y-m');
                $hasCompletion = self::hasCompletionFor($task['id'], $currentMonth);
                $daysUntil = (int)((strtotime($dueDate) - strtotime($today)) / 86400);
                
                if ($daysUntil <= 7 && $daysUntil >= 0) {
                    return ['status' => 'Due soon', 'days_until_due' => $daysUntil, 'due_date' => $dueDate];
                } elseif ($hasCompletion) {
                    return ['status' => 'Current', 'days_until_due' => $daysUntil, 'due_date' => $dueDate];
                } elseif ($daysUntil < 0) {
                    return ['status' => 'Past Due', 'days_until_due' => $daysUntil, 'due_date' => $dueDate];
                } else {
                    return ['status' => 'Not complete', 'days_until_due' => $daysUntil, 'due_date' => $dueDate];
                }

            case 'periodic':
                $latestCompletion = self::getLatestCompletion($task['id']);
                
                if (!$latestCompletion) {
                    return ['status' => 'New', 'days_until_due' => null, 'due_date' => null];
                }
                
                $periodicDays = (int)($task['periodic_days'] ?? 0);
                if ($periodicDays <= 0) {
                    return ['status' => 'Not complete', 'days_until_due' => null, 'due_date' => null];
                }
                
                $lastCompletedOn = $latestCompletion['completed_on'];
                $daysSinceCompletion = (int)((strtotime($today) - strtotime($lastCompletedOn)) / 86400);
                $daysUntil = $periodicDays - $daysSinceCompletion;
                
                // Calculate next due date
                $nextDueDate = date('Y-m-d', strtotime($lastCompletedOn . ' + ' . $periodicDays . ' days'));
                
                if ($daysSinceCompletion > $periodicDays) {
                    return ['status' => 'Past Due', 'days_until_due' => $daysUntil, 'due_date' => $nextDueDate];
                } elseif ($daysUntil <= 7 && $daysUntil >= 0) {
                    return ['status' => 'Due soon', 'days_until_due' => $daysUntil, 'due_date' => $nextDueDate];
                } else {
                    return ['status' => 'Not complete', 'days_until_due' => $daysUntil, 'due_date' => $nextDueDate];
                }

            default:
                return ['status' => 'Unknown', 'days_until_due' => null, 'due_date' => null];
        }
    }

    /**
     * Get default completed_for value based on recurrence type
     */
    public static function getDefaultCompletedFor(array $task, ?string $completedOn = null): string {
        if (!$completedOn) {
            $completedOn = date('Y-m-d');
        }
        
        switch ($task['recurrence_type']) {
            case 'one-off':
                return $task['date'] ?? $completedOn;
                
            case 'annual':
                // Use the current year
                return date('Y', strtotime($completedOn));
                
            case 'monthly':
                // Use year-month
                return date('Y-m', strtotime($completedOn));
                
            case 'periodic':
                return $completedOn;
                
            default:
                return $completedOn;
        }
    }

    /**
     * Create a new task
     */
    public static function createTask(?UserContext $ctx, array $data): int {
        if (!$ctx) {
            throw new RuntimeException('Login required');
        }

        $name = trim($data['name'] ?? '');
        $instructions = trim($data['instructions'] ?? '');
        $recurrenceType = $data['recurrence_type'] ?? 'one-off';
        $date = !empty($data['date']) ? $data['date'] : null;
        $annualDate = !empty($data['annual_date']) ? $data['annual_date'] : null;
        $dayOfMonth = !empty($data['day_of_month']) ? (int)$data['day_of_month'] : null;
        $periodicDays = !empty($data['periodic_days']) ? (int)$data['periodic_days'] : null;

        if ($name === '') {
            throw new InvalidArgumentException('Task name is required.');
        }

        if (!in_array($recurrenceType, ['one-off', 'annual', 'monthly', 'periodic'])) {
            throw new InvalidArgumentException('Invalid recurrence type.');
        }

        $sql = 'INSERT INTO tasks (name, instructions, recurrence_type, date, annual_date, day_of_month, periodic_days, created_by_user_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
        $st = self::pdo()->prepare($sql);
        $st->execute([$name, $instructions, $recurrenceType, $date, $annualDate, $dayOfMonth, $periodicDays, $ctx->id]);
        
        $taskId = (int)self::pdo()->lastInsertId();
        
        // Add responsible users if provided
        if (!empty($data['responsible_user_ids']) && is_array($data['responsible_user_ids'])) {
            foreach ($data['responsible_user_ids'] as $userId) {
                self::addResponsibleUser($taskId, (int)$userId);
            }
        }
        
        self::log('task.create', $taskId, ['name' => $name, 'recurrence_type' => $recurrenceType]);
        
        return $taskId;
    }

    /**
     * Update a task
     */
    public static function updateTask(?UserContext $ctx, int $taskId, array $data): bool {
        if (!$ctx) {
            throw new RuntimeException('Login required');
        }

        $name = trim($data['name'] ?? '');
        $instructions = trim($data['instructions'] ?? '');
        $recurrenceType = $data['recurrence_type'] ?? 'one-off';
        $date = !empty($data['date']) ? $data['date'] : null;
        $annualDate = !empty($data['annual_date']) ? $data['annual_date'] : null;
        $dayOfMonth = !empty($data['day_of_month']) ? (int)$data['day_of_month'] : null;
        $periodicDays = !empty($data['periodic_days']) ? (int)$data['periodic_days'] : null;
        $completed = !empty($data['completed']) ? 1 : 0;

        if ($name === '') {
            throw new InvalidArgumentException('Task name is required.');
        }

        $sql = 'UPDATE tasks SET name = ?, instructions = ?, recurrence_type = ?, date = ?, 
                annual_date = ?, day_of_month = ?, periodic_days = ?, completed = ? WHERE id = ?';
        $st = self::pdo()->prepare($sql);
        $ok = $st->execute([$name, $instructions, $recurrenceType, $date, $annualDate, $dayOfMonth, $periodicDays, $completed, $taskId]);
        
        if ($ok) {
            self::log('task.update', $taskId, ['name' => $name]);
        }
        
        return $ok;
    }

    /**
     * Get a task by ID
     */
    public static function getTask(int $taskId): ?array {
        $sql = 'SELECT * FROM tasks WHERE id = ?';
        $st = self::pdo()->prepare($sql);
        $st->execute([$taskId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /**
     * List all active tasks with their status
     */
    public static function listTasks(bool $includeCompleted = false): array {
        $sql = 'SELECT * FROM tasks';
        if (!$includeCompleted) {
            $sql .= ' WHERE completed = 0';
        }
        $sql .= ' ORDER BY name';
        
        $st = self::pdo()->prepare($sql);
        $st->execute();
        $tasks = $st->fetchAll();
        
        // Add status information to each task
        foreach ($tasks as &$task) {
            $statusInfo = self::calculateTaskStatus($task);
            $task['status'] = $statusInfo['status'];
            $task['days_until_due'] = $statusInfo['days_until_due'];
            $task['due_date'] = $statusInfo['due_date'];
        }
        
        return $tasks;
    }

    /**
     * Delete a task
     */
    public static function deleteTask(?UserContext $ctx, int $taskId): bool {
        if (!$ctx) {
            throw new RuntimeException('Login required');
        }

        $st = self::pdo()->prepare('DELETE FROM tasks WHERE id = ?');
        $ok = $st->execute([$taskId]);
        
        if ($ok) {
            self::log('task.delete', $taskId);
        }
        
        return $ok;
    }

    /**
     * Add a task completion
     */
    public static function addCompletion(?UserContext $ctx, int $taskId, string $completedOn, string $completedFor, ?string $notes = null): int {
        if (!$ctx) {
            throw new RuntimeException('Login required');
        }

        $sql = 'INSERT INTO task_completions (task_id, user_id, completed_on, completed_for, notes)
                VALUES (?, ?, ?, ?, ?)';
        $st = self::pdo()->prepare($sql);
        $st->execute([$taskId, $ctx->id, $completedOn, $completedFor, $notes]);
        
        $completionId = (int)self::pdo()->lastInsertId();
        self::log('task.complete', $taskId, ['completed_for' => $completedFor]);
        
        return $completionId;
    }

    /**
     * Update a task completion
     */
    public static function updateCompletion(?UserContext $ctx, int $completionId, string $completedOn, string $completedFor, ?string $notes = null): bool {
        if (!$ctx) {
            throw new RuntimeException('Login required');
        }

        $sql = 'UPDATE task_completions SET completed_on = ?, completed_for = ?, notes = ? WHERE id = ?';
        $st = self::pdo()->prepare($sql);
        $ok = $st->execute([$completedOn, $completedFor, $notes, $completionId]);
        
        if ($ok) {
            self::log('task.completion_update', null, ['completion_id' => $completionId]);
        }
        
        return $ok;
    }

    /**
     * Get a task completion by ID
     */
    public static function getCompletion(int $completionId): ?array {
        $sql = 'SELECT * FROM task_completions WHERE id = ?';
        $st = self::pdo()->prepare($sql);
        $st->execute([$completionId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /**
     * List completions for a task
     */
    public static function listCompletions(int $taskId): array {
        $sql = 'SELECT tc.*, u.first_name, u.last_name 
                FROM task_completions tc
                LEFT JOIN users u ON tc.user_id = u.id
                WHERE tc.task_id = ?
                ORDER BY tc.completed_on DESC';
        $st = self::pdo()->prepare($sql);
        $st->execute([$taskId]);
        return $st->fetchAll();
    }

    /**
     * Delete a task completion
     */
    public static function deleteCompletion(?UserContext $ctx, int $completionId): bool {
        if (!$ctx) {
            throw new RuntimeException('Login required');
        }

        $st = self::pdo()->prepare('DELETE FROM task_completions WHERE id = ?');
        $ok = $st->execute([$completionId]);
        
        if ($ok) {
            self::log('task.completion_delete', null, ['completion_id' => $completionId]);
        }
        
        return $ok;
    }

    /**
     * Add a responsible user to a task
     */
    public static function addResponsibleUser(int $taskId, int $userId): bool {
        try {
            $sql = 'INSERT INTO task_responsible_users (task_id, user_id) VALUES (?, ?)';
            $st = self::pdo()->prepare($sql);
            $st->execute([$taskId, $userId]);
            return true;
        } catch (PDOException $e) {
            // Ignore duplicate key errors
            if ($e->getCode() == 23000) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Remove a responsible user from a task
     */
    public static function removeResponsibleUser(int $taskId, int $userId): bool {
        $sql = 'DELETE FROM task_responsible_users WHERE task_id = ? AND user_id = ?';
        $st = self::pdo()->prepare($sql);
        return $st->execute([$taskId, $userId]);
    }

    /**
     * Get all responsible users for a task
     */
    public static function getResponsibleUsers(int $taskId): array {
        $sql = 'SELECT u.id, u.first_name, u.last_name, u.email
                FROM task_responsible_users tru
                JOIN users u ON tru.user_id = u.id
                WHERE tru.task_id = ?
                ORDER BY u.last_name, u.first_name';
        $st = self::pdo()->prepare($sql);
        $st->execute([$taskId]);
        return $st->fetchAll();
    }

    /**
     * Set responsible users for a task (replaces all existing)
     */
    public static function setResponsibleUsers(?UserContext $ctx, int $taskId, array $userIds): bool {
        if (!$ctx) {
            throw new RuntimeException('Login required');
        }

        $pdo = self::pdo();
        
        try {
            $pdo->beginTransaction();
            
            // Remove all existing
            $st = $pdo->prepare('DELETE FROM task_responsible_users WHERE task_id = ?');
            $st->execute([$taskId]);
            
            // Add new ones
            foreach ($userIds as $userId) {
                self::addResponsibleUser($taskId, (int)$userId);
            }
            
            $pdo->commit();
            self::log('task.responsible_users_update', $taskId, ['user_ids' => $userIds]);
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
