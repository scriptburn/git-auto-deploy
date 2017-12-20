<?php
namespace App\Services;

use JeremyKendall\Password\PasswordValidator;
use \Scriptburn\UpdateDb;

class DbUpdateService extends UpdateDb
{
    public function update_routine_1()
    {
        $user_table[] = "CREATE TABLE `users` (
  `id` bigint(10) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255)  NULL,
  `role` varchar(255)  NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

        $user_table[] = "ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);";
        $user_table[] = " ALTER TABLE `users`
  MODIFY `id` bigint(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;";

        $projects_table[] = "CREATE TABLE `projects` (
  `id` bigint(20) NOT NULL,
  `type` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `branch` varchar(255) NOT NULL,
  `path` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `status` int(1) NOT NULL,
  `secret` varchar(255)  NULL,
  `pre_hook` varchar(255)  NULL,
  `post_hook` varchar(255)  NULL,
  `email_result` varchar(255)  NULL,
  `uid` bigint(10)  NULL,
    `last_hook_status` int(1)  NULL,
  `last_hook_time` datetime  NULL,
  `last_hook_duration` int(5)  NULL,
  `last_hook_log` text  NULL,
    `composer_update` int(1)  NULL


) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        $projects_table[] = "ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`);";
        $projects_table[] = "ALTER TABLE `projects`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;";

        $create_table = function ($table)
        {

            $table = is_array($table) ? $table : [$table];
            foreach ($table as $tbl)
            {
                 $this->pdo->query($tbl);

            }
        };
        if (!$this->tableExists('users'))
        {
            $create_table($user_table);

            $validator = new PasswordValidator();
            $this->pdo->insert(['username', 'password', 'role'])
                ->into('users')
                ->values(['admin', $validator->rehash('admin'), 'admin'])
                ->execute(false);
        }
        if (!$this->tableExists('projects'))
        {
            $create_table($projects_table);
        }
         
        p_l("in update_routine_1");

        return true;
    }
    public function update_routine_2()
    {
        p_l("in update_routine_2");
        $updates[] = "ALTER TABLE `users` CHANGE `email` `email` VARCHAR(255)  NULL DEFAULT NULL";
        $updates[] = "ALTER TABLE `users` CHANGE `role` `role` VARCHAR(255)  NULL DEFAULT NULL";
        $updates[] = "ALTER TABLE `projects` CHANGE `secret` `secret` VARCHAR(255)  NULL DEFAULT NULL";

        $updates[] = "ALTER TABLE `projects` CHANGE `pre_hook` `pre_hook` VARCHAR(255)  NULL DEFAULT NULL";
        $updates[] = "ALTER TABLE `projects` CHANGE `post_hook` `post_hook` VARCHAR(255)  NULL DEFAULT NULL";
        $updates[] = "ALTER TABLE `projects` CHANGE `email_result` `email_result` VARCHAR(255)  NULL DEFAULT NULL";

        $updates[] = "ALTER TABLE `projects` CHANGE `uid` `uid` bigint(10)  NULL DEFAULT NULL";
        $updates[] = "ALTER TABLE `projects` CHANGE `last_hook_status` `last_hook_status` int(1)  NULL DEFAULT NULL";

        $updates[] = "ALTER TABLE `projects` CHANGE `last_hook_time` `last_hook_time` datetime  NULL DEFAULT NULL";

        $updates[] = "ALTER TABLE `projects` CHANGE `last_hook_duration` `last_hook_duration` int(5)  NULL DEFAULT NULL";

        $updates[] = "ALTER TABLE `projects` CHANGE `last_hook_log` `last_hook_log` text  NULL DEFAULT NULL";
        $updates[] = "ALTER TABLE `projects` CHANGE `composer_update` `composer_update` int(1)  NULL DEFAULT NULL";

        $this->execute($updates);
        return true;
    }

}
