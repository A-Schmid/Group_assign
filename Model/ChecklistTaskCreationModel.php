<?php

namespace Kanboard\Plugin\Group_assign\Model;

use Kanboard\Core\Base;
use Kanboard\Model\TaskCreationModel;
use Kanboard\Model\TaskModel;
use Kanboard\Model\TaskPosistionModel;
use Kanboard\Model\TaskTagModel;
use Kanboard\Model\SubTaskModel;
use Kanboard\Model\UserModel;
use Kanboard\Model\GroupMemberModel;

/**
 * Checklist Task Model
 *
 * @package  Kanboard\Plugin\Group_assign
 * @author   Andreas Schmid
 */
class ChecklistTaskCreationModel extends TaskCreationModel
{
    public function create(array $values)
    {
        $position = empty($values['position']) ? 0 : $values['position'];
        $tags = array();

        if (isset($values['tags'])) {
            $tags = $values['tags'];
            unset($values['tags']);
        }

        if (isset($values['is_checklist'])) {
            $is_checklist = $values['is_checklist'];
            unset($values['is_checklist']);
        }

        $this->prepare($values);
        $task_id = $this->db->table(TaskModel::TABLE)->persist($values);

        if ($task_id !== false) {
            if ($position > 0 && $values['position'] > 1) {
                $this->taskPositionModel->movePosition($values['project_id'], $task_id, $values['column_id'], $position, $values['swimlane_id'], false);
            }

            if (! empty($tags)) {
                $this->taskTagModel->save($values['project_id'], $task_id, $tags);
            }

            $this->queueManager->push($this->taskEventJob->withParams(
                $task_id,
                array(TaskModel::EVENT_CREATE_UPDATE, TaskModel::EVENT_CREATE)
            ));

            // create checklist
            if (! empty($is_checklist)) {
                if (isset($values['owner_gp'])) {
                    $members = $this->groupMemberModel->getMembers($values['owner_gp']);

                    foreach ($members as $member) { 
                        $member_id = $member['user_id'];
                        $member_name = $member['username'];

                        $new_subtask = array("title" => $member_name, "user_id" => $member_id, "time_estimated" => "", "task_id"=> $task_id );

                        $this->subtaskModel->create($new_subtask);
                    }
                }
            }
        }

        $this->hook->reference('model:task:creation:aftersave', $task_id);
        return (int) $task_id;
    }
}

?>
