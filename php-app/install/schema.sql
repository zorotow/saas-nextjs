-- SaaS PHP Application Database Schema
-- MySQL 5.7+ / MariaDB 10.2+

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Users table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NULL,
    `email` VARCHAR(255) NOT NULL,
    `password_hash` TEXT NOT NULL,
    `role` VARCHAR(20) NOT NULL DEFAULT 'member',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `users_email_unique` (`email`),
    KEY `users_deleted_at_index` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Teams table
CREATE TABLE IF NOT EXISTS `teams` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `stripe_customer_id` VARCHAR(255) NULL,
    `stripe_subscription_id` VARCHAR(255) NULL,
    `stripe_product_id` VARCHAR(255) NULL,
    `plan_name` VARCHAR(50) NULL,
    `subscription_status` VARCHAR(20) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `teams_stripe_customer_id_unique` (`stripe_customer_id`),
    UNIQUE KEY `teams_stripe_subscription_id_unique` (`stripe_subscription_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Team Members table
CREATE TABLE IF NOT EXISTS `team_members` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `team_id` INT UNSIGNED NOT NULL,
    `role` VARCHAR(50) NOT NULL,
    `joined_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `team_members_user_id_index` (`user_id`),
    KEY `team_members_team_id_index` (`team_id`),
    CONSTRAINT `team_members_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `team_members_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity Logs table
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `team_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NULL,
    `action` TEXT NOT NULL,
    `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ip_address` VARCHAR(45) NULL,
    PRIMARY KEY (`id`),
    KEY `activity_logs_team_id_index` (`team_id`),
    KEY `activity_logs_user_id_index` (`user_id`),
    KEY `activity_logs_timestamp_index` (`timestamp`),
    CONSTRAINT `activity_logs_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
    CONSTRAINT `activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Invitations table
CREATE TABLE IF NOT EXISTS `invitations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `team_id` INT UNSIGNED NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `role` VARCHAR(50) NOT NULL,
    `invited_by` INT UNSIGNED NOT NULL,
    `invited_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
    PRIMARY KEY (`id`),
    KEY `invitations_team_id_index` (`team_id`),
    KEY `invitations_email_index` (`email`),
    KEY `invitations_invited_by_index` (`invited_by`),
    CONSTRAINT `invitations_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
    CONSTRAINT `invitations_invited_by_foreign` FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
