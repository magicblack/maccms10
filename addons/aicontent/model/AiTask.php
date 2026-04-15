<?php

namespace addons\aicontent\model;

use think\Model;

/**
 * Model for the mac_ai_task table.
 * Tracks every AI generation request (single or batch).
 */
class AiTask extends Model
{
    protected $table      = 'mac_ai_task';
    protected $pk         = 'id';
    protected $autoWriteTimestamp = false; // We manage timestamps manually

    // Task status constants
    const STATUS_PENDING = 0;
    const STATUS_DONE    = 1;
    const STATUS_ERROR   = 2;

    /**
     * Create a new pending task record.
     */
    public static function createTask(
        string $contentType,
        int    $contentId,
        string $contentName,
        string $provider,
        string $model,
        string $fields = 'description,tags,seo_title'
    ): self {
        $task = new self();
        $task->content_type = $contentType;
        $task->content_id   = $contentId;
        $task->content_name = $contentName;
        $task->provider     = $provider;
        $task->model        = $model;
        $task->fields       = $fields;
        $task->status       = self::STATUS_PENDING;
        $task->created_at   = date('Y-m-d H:i:s');
        $task->save();
        return $task;
    }

    /**
     * Mark task as successfully completed with the AI result JSON.
     */
    public function markDone(string $resultJson)
    {
        $this->status     = self::STATUS_DONE;
        $this->result     = $resultJson;
        $this->error_msg  = null;
        $this->updated_at = date('Y-m-d H:i:s');
        $this->save();
    }

    /**
     * Mark task as failed with an error message.
     */
    public function markError(string $errorMsg)
    {
        $this->status     = self::STATUS_ERROR;
        $this->error_msg  = mb_substr($errorMsg, 0, 499);
        $this->updated_at = date('Y-m-d H:i:s');
        $this->save();
    }

    /**
     * Get paginated task history.
     */
    public static function getHistory(int $page = 1, int $pageSize = 20): array
    {
        $total = self::count();
        $list  = self::order('id', 'desc')
                     ->limit(($page - 1) * $pageSize, $pageSize)
                     ->select()
                     ->toArray();
        return ['total' => $total, 'list' => $list];
    }

    /**
     * Human-readable status label.
     */
    public function getStatusLabelAttr(): string
    {
        switch ((int) $this->status) {
            case self::STATUS_PENDING: return lang('Pending');
            case self::STATUS_DONE:    return lang('Done');
            case self::STATUS_ERROR:   return lang('Error');
            default:                   return lang('Unknown');
        }
    }
}
