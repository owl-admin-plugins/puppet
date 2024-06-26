<?php

use ManoCode\Puppet\Http\Controllers;
use Illuminate\Support\Facades\Route;

Route::get('puppet', [Controllers\PuppetController::class, 'index']);

/**
 * 获取设备列表
 */
Route::get('/puppet/get-goods-sku',[Controllers\PuppetEquipmentController::class,'getEquipment']);
/**
 * 搜索账号
 */
Route::get('/puppet/get-huolala-account',[Controllers\PuppetHuolalaAccountController::class,'getAccount']);
/**
 * 批量重启
 */
Route::post('/puppet/equipment/reboot/{ids}',[Controllers\PuppetEquipmentController::class,'reboot']);

// 设备管理
Route::resource('puppet_equipment', \ManoCode\Puppet\Http\Controllers\PuppetEquipmentController::class);
// 任务管理
Route::resource('puppet_task', \ManoCode\Puppet\Http\Controllers\PuppetTaskController::class);
// 货拉拉账号
Route::resource('puppet_huolala_account', \ManoCode\Puppet\Http\Controllers\PuppetHuolalaAccountController::class);
