<?php

namespace Uupt\Puppet\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Uupt\Puppet\Models\PuppetEquipment;
use Uupt\Puppet\Models\PuppetHuolalaAccount;
use Uupt\Puppet\Models\PuppetTask;
use function Hyperf\Support\env;

/**
 *
 */
class ApiController extends Controller
{
    public function getTask(Request $request): array
    {
        $uuid = strval($request->input('uuid'));
        if (!($puppetEquipment = PuppetEquipment::query()->where(['uuid' => $uuid])->where('status', '<>', 3)->first())) {
            return [
                'status' => 'error',
                'msg' => '设备不存在',
                'data' => []
            ];
        }
        // 更新状态
        PuppetEquipment::query()->where(['uuid' => $uuid])->update(['status' => 1]);
        // 获取采集任务
        if ($task = PuppetTask::query()->where(function ($query) use ($puppetEquipment) {
            $query->where(['equipment_id' => $puppetEquipment->getAttribute('id')])->orWhereNull('equipment_id');
        })->where('status', 1)->first()) {
            // 更新状态为 处理中
            PuppetTask::query()->where(['id' => $task->getAttribute('id')])->update(['status' => 2, 'equipment_id' => $puppetEquipment->getAttribute('id')]);
            $task = $task->toArray();
            $task['content'] = json_decode($task['content'], true);
            $task['content']['account'] = PuppetHuolalaAccount::query()->where(['id' => $task['content']['account']])->select([
                'mobile',
                'password'
            ])->first();
            $task['content']['start_address'] = $task['content']['city']['city'] . $task['content']['start_address'];
            $task['content']['end_address'] = $task['content']['city']['city'] . $task['content']['end_address'];
            return [
                'status' => 'success',
                'msg' => '获取成功',
                'task' => $task
            ];
        }
        return ['status' => 'success', 'msg' => '暂无任务'];
    }

    public function pushResult(Request $request): array
    {
        $uuid = strval($request->input('uuid'));
        if (!($puppetEquipment = PuppetEquipment::query()->where(['uuid' => $uuid])->where('status', '<>', 3)->first())) {
            return [
                'status' => 'error',
                'msg' => '设备不存在',
                'data' => []
            ];
        }
        $task_id = strval($request->input('task_id'));
        if (strlen($task_id) <= 0) {
            return [
                'status' => 'error',
                'msg' => '任务ID不能为空'
            ];
        }
        // 只有执行中的任务可以 反馈结果
        if (!($taskInfo = PuppetTask::query()->where(['equipment_id' => $puppetEquipment->getAttribute('id'), 'task_id' => $task_id, 'status' => 2])->first())) {
            return [
                'status' => 'error',
                'msg' => '任务不存在',
                'data' => []
            ];
        }
        $result = strval($request->input('result'));
        if (strlen($result) <= 0) {
            return [
                'status' => 'error',
                'msg' => '结果不能为空',
                'data' => []
            ];
        }
        $result = json_decode($result, true);
        $result['time'] = date('Y-m-d H:i:s');
        $status = strval($request->input('status'));
        if (!in_array($status, ['success', 'error'])) {
            return [
                'status' => 'error',
                'msg' => '状态错误',
                'data' => []
            ];
        }
        $taskInfo->setAttribute('result', json_encode($result));
        $taskInfo->setAttribute('status', $status == 'success' ? 3 : 4);
        $taskInfo->save();
        return [
            'status' => 'success',
            'msg' => '反馈成功'
        ];
    }

    public function addTask(Request $request)
    {
        $puppetTask = new PuppetTask();
        $puppetTask->setAttribute('status', 1);
        $puppetTask->setAttribute('task_id', Str::random(64));
        $puppetTask->setAttribute('type', 1);
        $puppetTask->setAttribute('content', json_encode([
            'account' => 1,
            'city' => [
                'city' => $request->input('city'),
            ],
            'map_distance' => $request->input('map_distance'), // 地图导航距离
            'car_type' => $request->input('car_type'),
            'start_address' => $request->input('city') . $request->input('start_address'),
            'end_address' => $request->input('city') . $request->input('end_address'),
        ]));
        $puppetTask->save();
        return ['status' => 'success', 'msg' => '新增成功', 'data' => [
            'task_id' => $puppetTask->getAttribute('task_id')
        ]];
    }

    /**
     * 探针保活 TODO 定时清除 超过20秒没有心跳的 设备
     * @param Request $request
     * @return array|string[]
     */
    public function probe(Request $request)
    {
        $uuid = strval($request->input('uuid'));
        if (!($puppetEquipment = PuppetEquipment::query()->where(['uuid' => $uuid])->where('status', '<>', 3)->first())) {
            return [
                'status' => 'error',
                'msg' => '设备不存在',
                'data' => []
            ];
        }
        $puppetEquipment->setAttribute('status', 1);
        $puppetEquipment->save();
        return [
            'status' => 'success',
            'msg' => '操作成功'
        ];
    }

    public function queryTask(Request $request)
    {
        $task_lists = $request->query('task_lists');
        foreach ($task_lists as $key => $item) {
            $taskInfo = PuppetTask::query()->where(['task_id' => $item['task_id']])->first();
            if($taskInfo->getAttribute('status') === 4){
                $result = json_decode($taskInfo->getAttribute('result'),true);
                // 错误消息获取
                $task_lists[$key]['error_msg'] = isset($result['error_msg'])?strval($result['error_msg']):'-';
            }
            if($taskInfo->getAttribute('status') === 3){
                $result = json_decode($taskInfo->getAttribute('result'),true);
                $task_lists[$key]['price'] = isset($result['price'])?$result['price']:'';
                $task_lists[$key]['discount_price'] = isset($result['discount_price'])?$result['discount_price']:'';
            }
            $task_lists[$key]['status'] = array_column(admin_dict()->getOptions('puppet.task.status'), 'label', 'value')[$taskInfo->getAttribute('status')];
        }
        return [
            'status' => 'success',
            'msg' => '查询成功',
            'data' => [
                'task_lists' => $task_lists
            ]
        ];
    }
}
