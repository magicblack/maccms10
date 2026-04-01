CREATE TABLE IF NOT EXISTS `mac_ai_task` (
  `id`           int(11) NOT NULL AUTO_INCREMENT,
  `content_type` varchar(20)  NOT NULL DEFAULT 'video' COMMENT 'video | article | topic',
  `content_id`   int(11)      NOT NULL DEFAULT 0,
  `content_name` varchar(255) NOT NULL DEFAULT '',
  `provider`     varchar(30)  NOT NULL DEFAULT '',
  `model`        varchar(60)  NOT NULL DEFAULT '',
  `fields`       varchar(255) NOT NULL DEFAULT '' COMMENT 'Comma-separated fields to generate: description,tags,seo_title',
  `status`       tinyint(1)   NOT NULL DEFAULT 0 COMMENT '0=pending, 1=done, 2=error',
  `result`       text                  DEFAULT NULL COMMENT 'Raw JSON response from AI',
  `error_msg`    varchar(500)          DEFAULT NULL,
  `created_at`   datetime     NOT NULL,
  `updated_at`   datetime              DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status`       (`status`),
  KEY `idx_content`      (`content_type`, `content_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI content generation task queue';
