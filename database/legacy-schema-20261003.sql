-- Empty MySQL/MariaDB schema. Select an empty pre_defense_system database before importing.
-- No DROP, DELETE, INSERT or demo records.
SET NAMES utf8mb4;

create table `migrations` (`id` int unsigned not null auto_increment primary key, `migration` varchar(255) not null, `batch` int not null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

create table `organizations` (`id` bigint unsigned not null auto_increment primary key, `name` varchar(255) not null, `description` text null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

create table `users` (`id` bigint unsigned not null auto_increment primary key, `name` varchar(255) not null, `email` varchar(255) not null, `username` varchar(255) not null, `password` varchar(255) not null, `role` varchar(255) not null default 'student', `account_type` varchar(255) not null default 'student', `student_id` varchar(255) null, `course` varchar(255) null, `year_level` varchar(255) null, `office` varchar(255) null, `position` varchar(255) null, `phone` varchar(255) null, `avatar` varchar(255) null, `organization_id` bigint unsigned null, `email_verified_at` timestamp null, `profile_complete` tinyint(1) not null default '0', `two_factor` tinyint(1) not null default '0', `preferences` json null, `remember_token` varchar(100) null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `users` add constraint `users_organization_id_foreign` foreign key (`organization_id`) references `organizations` (`id`) on delete set null;

alter table `users` add unique `users_email_unique`(`email`);

alter table `users` add unique `users_username_unique`(`username`);

alter table `users` add unique `users_student_id_unique`(`student_id`);

create table `password_reset_tokens` (`email` varchar(255) not null, `token` varchar(255) not null, `created_at` timestamp null, primary key (`email`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

create table `service_requests` (`id` bigint unsigned not null auto_increment primary key, `user_id` bigint unsigned not null, `ticket` varchar(255) not null, `kind` varchar(255) not null, `status` varchar(255) not null default 'pending', `details` json not null, `files` json null, `review_note` text null, `reviewed_by` bigint unsigned null, `reviewed_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `service_requests` add constraint `service_requests_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete cascade;

alter table `service_requests` add constraint `service_requests_reviewed_by_foreign` foreign key (`reviewed_by`) references `users` (`id`) on delete set null;

alter table `service_requests` add unique `service_requests_ticket_unique`(`ticket`);

alter table `service_requests` add index `service_requests_kind_index`(`kind`);

alter table `service_requests` add index `service_requests_status_index`(`status`);

create table `assignments` (`id` bigint unsigned not null auto_increment primary key, `user_id` bigint unsigned not null, `record_number` varchar(255) not null, `offense` varchar(255) not null, `severity` varchar(255) not null, `department` varchar(255) not null, `supervisor` varchar(255) not null, `required_minutes` int unsigned not null, `status` varchar(255) not null default 'active', `note` text null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `assignments` add constraint `assignments_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete cascade;

alter table `assignments` add unique `assignments_record_number_unique`(`record_number`);

create table `events` (`id` bigint unsigned not null auto_increment primary key, `title` varchar(255) not null, `description` text not null, `venue` varchar(255) not null, `starts_at` datetime not null, `ends_at` datetime not null, `points` int unsigned not null default '50', `capacity` int unsigned not null default '100', `status` varchar(255) not null default 'published', `type` varchar(255) not null default 'leadership', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

create table `attendances` (`id` bigint unsigned not null auto_increment primary key, `user_id` bigint unsigned not null, `assignment_id` bigint unsigned null, `event_id` bigint unsigned null, `time_in` timestamp null, `time_out` timestamp null, `minutes` int unsigned not null default '0', `reflection` text null, `photo_in` varchar(255) null, `photo_out` varchar(255) null, `status` varchar(255) not null default 'registered', `review_note` text null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `attendances` add constraint `attendances_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete cascade;

alter table `attendances` add constraint `attendances_assignment_id_foreign` foreign key (`assignment_id`) references `assignments` (`id`) on delete cascade;

alter table `attendances` add constraint `attendances_event_id_foreign` foreign key (`event_id`) references `events` (`id`) on delete cascade;

alter table `attendances` add unique `attendances_user_id_event_id_unique`(`user_id`, `event_id`);

create table `journals` (`id` bigint unsigned not null auto_increment primary key, `assignment_id` bigint unsigned not null, `module` int unsigned not null, `answer` text not null, `status` varchar(255) not null default 'pending', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `journals` add constraint `journals_assignment_id_foreign` foreign key (`assignment_id`) references `assignments` (`id`) on delete cascade;

alter table `journals` add unique `journals_assignment_id_module_unique`(`assignment_id`, `module`);

create table `points` (`id` bigint unsigned not null auto_increment primary key, `user_id` bigint unsigned not null, `source` varchar(255) not null, `amount` int not null, `reason` varchar(255) not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `points` add constraint `points_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete cascade;

alter table `points` add unique `points_source_unique`(`source`);

create table `posts` (`id` bigint unsigned not null auto_increment primary key, `organization_id` bigint unsigned not null, `user_id` bigint unsigned not null, `body` text not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `posts` add constraint `posts_organization_id_foreign` foreign key (`organization_id`) references `organizations` (`id`) on delete cascade;

alter table `posts` add constraint `posts_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete cascade;

create table `comments` (`id` bigint unsigned not null auto_increment primary key, `post_id` bigint unsigned not null, `user_id` bigint unsigned not null, `body` text not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `comments` add constraint `comments_post_id_foreign` foreign key (`post_id`) references `posts` (`id`) on delete cascade;

alter table `comments` add constraint `comments_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete cascade;

create table `notifications` (`id` char(36) not null, `type` varchar(255) not null, `notifiable_type` varchar(255) not null, `notifiable_id` bigint unsigned not null, `data` text not null, `read_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null, primary key (`id`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `notifications` add index `notifications_notifiable_type_notifiable_id_index`(`notifiable_type`, `notifiable_id`);

create table `audit_logs` (`id` bigint unsigned not null auto_increment primary key, `user_id` bigint unsigned null, `action` varchar(255) not null, `subject` varchar(255) not null, `details` text null, `created_at` timestamp not null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `audit_logs` add constraint `audit_logs_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete set null;

create table `settings` (`key` varchar(255) not null, `value` text not null, primary key (`key`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

create table `reports` (`id` bigint unsigned not null auto_increment primary key, `user_id` bigint unsigned not null, `title` varchar(255) not null, `academic_year` varchar(255) not null, `semester` varchar(255) not null, `summary` text not null, `snapshot` json not null, `status` varchar(255) not null default 'pending', `review_note` text null, `reviewed_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `reports` add constraint `reports_user_id_foreign` foreign key (`user_id`) references `users` (`id`);

alter table `reports` add constraint `reports_reviewed_by_foreign` foreign key (`reviewed_by`) references `users` (`id`);

alter table `users` add `employee_id` varchar(255) null;

alter table `users` add `ward_name` varchar(255) null;

alter table `users` add `ward_student_id` varchar(255) null;

create table `announcements` (`id` bigint unsigned not null auto_increment primary key, `user_id` bigint unsigned not null, `unit` varchar(255) not null, `title` varchar(180) not null, `body` text not null, `published_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `announcements` add constraint `announcements_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete cascade;

alter table `announcements` add index `announcements_published_at_index`(`published_at`);

create table `proposal_drafts` (`id` bigint unsigned not null auto_increment primary key, `user_id` bigint unsigned not null, `details` json not null, `files` json not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `proposal_drafts` add constraint `proposal_drafts_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete cascade;

alter table `proposal_drafts` add unique `proposal_drafts_user_id_unique`(`user_id`);

alter table `organizations` add `moderators` json null;

