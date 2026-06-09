-- ============================================================
-- JengaFund Database Schema
-- Crowdfunding platform for student innovation projects
-- MySQL 8.0+
-- ============================================================

CREATE DATABASE IF NOT EXISTS jengafund
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE jengafund;

-- ------------------------------------------------------------
-- USERS — identity + lifecycle (all roles)
-- ------------------------------------------------------------

CREATE TABLE users (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email             VARCHAR(255)    NOT NULL,
  password_hash     VARCHAR(255)    NOT NULL,
  role              ENUM('student', 'donor', 'admin') NOT NULL,
  full_name         VARCHAR(255)    NOT NULL,
  phone_number      VARCHAR(20)     NULL,
  email_verified_at DATETIME        NULL,
  is_active         TINYINT(1)      NOT NULL DEFAULT 1,
  deleted_at        DATETIME        NULL,
  created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY (email)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- ACCOUNT APPROVALS — admin gate for everyone (1:1 with users)
-- ------------------------------------------------------------

CREATE TABLE account_approvals (
  user_id           BIGINT UNSIGNED NOT NULL,
  approval_status   ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  approved_by       BIGINT UNSIGNED NULL,
  approved_at       DATETIME        NULL,
  rejection_reason  TEXT            NULL,
  created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (user_id),
  CONSTRAINT fk_account_approvals_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE CASCADE,
  CONSTRAINT fk_account_approvals_approved_by
    FOREIGN KEY (approved_by) REFERENCES users (id)
    ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- STUDENTS — documents only (role = student)
-- ------------------------------------------------------------

CREATE TABLE students (
  user_id               BIGINT UNSIGNED NOT NULL,
  mpesa_number          VARCHAR(20)     NULL,
  kcse_certificate_path VARCHAR(500)  NULL,
  id_photo_path         VARCHAR(500)    NULL,
  created_at            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (user_id),
  CONSTRAINT fk_students_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE email_verifications (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED NOT NULL,
  code        VARCHAR(10)     NOT NULL,
  expires_at  DATETIME        NOT NULL,
  verified_at DATETIME        NULL,
  created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  CONSTRAINT fk_email_verifications_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- CAMPAIGNS & MILESTONES
-- ------------------------------------------------------------

CREATE TABLE campaigns (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id       BIGINT UNSIGNED NOT NULL,
  title            VARCHAR(255)    NOT NULL,
  description      TEXT            NOT NULL,
  category         VARCHAR(100)    NULL,
  goal_amount      DECIMAL(12, 2)  NOT NULL,
  funds_raised     DECIMAL(12, 2)  NOT NULL DEFAULT 0.00,
  disbursement_type ENUM('full', 'milestone') NOT NULL DEFAULT 'milestone'
    COMMENT 'full if goal_amount < 2000, else milestone — set on create',
  starts_at        DATETIME        NOT NULL COMMENT 'Campaign opens for donations',
  ends_at          DATETIME        NOT NULL COMMENT 'Campaign closes — triggers awaiting_disbursement',
  status           ENUM(
                     'draft',
                     'pending_approval',
                     'approved',
                     'rejected',
                     'active',
                     'awaiting_disbursement',
                     'completed',
                     'cancelled'
                   ) NOT NULL DEFAULT 'draft',
  rejection_reason TEXT            NULL,
  reviewed_by      BIGINT UNSIGNED NULL,
  reviewed_at      DATETIME        NULL,
  created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  CONSTRAINT fk_campaigns_student
    FOREIGN KEY (student_id) REFERENCES users (id),
  CONSTRAINT fk_campaigns_reviewed_by
    FOREIGN KEY (reviewed_by) REFERENCES users (id)
    ON DELETE SET NULL,
  CONSTRAINT chk_campaigns_goal_positive CHECK (goal_amount > 0),
  CONSTRAINT chk_campaigns_funds_non_negative CHECK (funds_raised >= 0),
  CONSTRAINT chk_campaigns_dates CHECK (ends_at > starts_at)
) ENGINE=InnoDB;

-- Campaign status transitions (enforced in app or scheduled job):
--   approved        → active              when NOW() >= starts_at
--   active          → awaiting_disbursement when NOW() >= ends_at OR funds_raised >= goal_amount
--   awaiting_disbursement → completed   when all milestones approved/disbursed

CREATE TABLE milestones (
  id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  campaign_id           BIGINT UNSIGNED NOT NULL,
  title                 VARCHAR(255)    NOT NULL,
  description           TEXT            NOT NULL,
  sequence_order        INT UNSIGNED    NOT NULL DEFAULT 1,
  disbursement_percent  DECIMAL(5, 2)   NOT NULL
    COMMENT 'Share of funds_raised to release e.g. 25.00 = 25%. Full-pay campaigns use 100.00',
  status                ENUM('pending', 'evidence_submitted', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  evidence_file_path    VARCHAR(500)    NULL,
  evidence_notes        TEXT            NULL,
  evidence_submitted_at DATETIME        NULL,
  evaluated_by          BIGINT UNSIGNED NULL,
  evaluated_at          DATETIME        NULL,
  evaluation_notes      TEXT            NULL,
  disbursed_amount      DECIMAL(12, 2)  NULL
    COMMENT 'Actual KES sent: funds_raised × (disbursement_percent / 100) at approval time',
  disbursed_at          DATETIME        NULL,
  created_at            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY (campaign_id, sequence_order),
  CONSTRAINT fk_milestones_campaign
    FOREIGN KEY (campaign_id) REFERENCES campaigns (id)
    ON DELETE CASCADE,
  CONSTRAINT fk_milestones_evaluated_by
    FOREIGN KEY (evaluated_by) REFERENCES users (id)
    ON DELETE SET NULL,
  CONSTRAINT chk_milestones_percent_range
    CHECK (disbursement_percent > 0 AND disbursement_percent <= 100)
) ENGINE=InnoDB;

-- Milestone rows for full-pay campaigns (< KES 2,000 goal):
--   one row, disbursement_percent = 100.00, title e.g. "Full payout"
-- Milestone rows for milestone campaigns (>= KES 2,000):
--   student defines splits e.g. 25 + 25 + 50 = 100 (enforced in app)

-- ------------------------------------------------------------
-- DONATIONS
-- ------------------------------------------------------------

CREATE TABLE donations (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  donor_id            BIGINT UNSIGNED NOT NULL,
  campaign_id         BIGINT UNSIGNED NOT NULL,
  amount              DECIMAL(12, 2)  NOT NULL,
  donor_phone         VARCHAR(20)     NOT NULL,
  status              ENUM('pending', 'success', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
  checkout_request_id VARCHAR(100)    NULL,
  created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  CONSTRAINT fk_donations_donor
    FOREIGN KEY (donor_id) REFERENCES users (id)
    ON DELETE RESTRICT,
  CONSTRAINT fk_donations_campaign
    FOREIGN KEY (campaign_id) REFERENCES campaigns (id),
  CONSTRAINT chk_donations_amount_positive CHECK (amount > 0)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- NOTIFICATIONS
-- ------------------------------------------------------------

CREATE TABLE notifications (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id           BIGINT UNSIGNED NOT NULL,
  type              ENUM(
                      'email_verification',
                      'account_approved',
                      'account_rejected',
                      'campaign_approved',
                      'campaign_rejected',
                      'donation_received',
                      'donation_progress',
                      'disbursement_completed',
                      'milestone_evaluated',
                      'project_update',
                      'donation_receipt'
                    ) NOT NULL,
  title             VARCHAR(255)    NOT NULL,
  message           TEXT            NOT NULL,
  channel           ENUM('email', 'sms', 'in_app') NOT NULL DEFAULT 'in_app',
  is_read           TINYINT(1)      NOT NULL DEFAULT 0,
  related_entity    VARCHAR(50)     NULL,
  related_entity_id BIGINT UNSIGNED NULL,
  sent_at           DATETIME        NULL,
  created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  CONSTRAINT fk_notifications_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE CASCADE
) ENGINE=InnoDB;
