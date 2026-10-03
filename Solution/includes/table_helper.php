<?php
/**
 * Database Table Helper Functions
 *
 * Provides shared functions for inspecting database table structures.
 * The getTableColumns() function was previously duplicated in at least
 * four files. It is consolidated here to remove the duplication.
 *
 * The function is guarded by function_exists() so that a second
 * include of this file does not trigger a redeclaration fatal.
 *
 * The function uses a strict allow-list of table names. A table name
 * that is not on the list is rejected and an empty array is returned.
 * This prevents a caller from using the function to inspect a table
 * outside the set the application is permitted to query.
 *
 * SOURCE: Technical Audit and Fixes Report.
 * SOURCE: Codebase review, duplication findings.
 *
 * @version 1.0
 */

if (!function_exists('getTableColumns'))
{
    /**
     * Returns the column names of a permitted database table.
     *
     * The function queries information_schema through a SHOW COLUMNS
     * statement. The table name is validated against a strict allow-list
     * before the query is issued.
     *
     * @param object $db        The database connection
     * @param string $tableName The table name
     * @return array The column names, or an empty array on failure
     */
    function getTableColumns($db, $tableName)
    {
        $allowedTables = array(
            'orders',
            'payments',
            'users',
            'vendors',
            'menu_items',
            'order_items',
            'complaints_compliments'
        );

        if (!in_array($tableName, $allowedTables, true))
        {
            if (function_exists('writeLog'))
            {
                writeLog(
                    "Attempted to access non-allowed table: $tableName",
                    "SECURITY"
                );
            }
            return array();
        }

        try
        {
            $columns = $db->fetchAll("SHOW COLUMNS FROM `$tableName`");
            $columnNames = array();

            foreach ($columns as $column)
            {
                $columnNames[] = $column['Field'];
            }

            return $columnNames;
        }
        catch (Exception $e)
        {
            if (function_exists('writeLog'))
            {
                writeLog(
                    "Failed to get table columns for $tableName: "
                        . $e->getMessage(),
                    "DATABASE_ERROR"
                );
            }
            return array();
        }
    }
}