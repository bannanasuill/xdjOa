<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RoleModel;
use App\Models\UserInviteCodeModel;
use App\Models\UserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function verifyInviteCode(Request $request): JsonResponse
    {
        if (! Schema::hasTable('user_invite_codes')) {
            return $this->fail(1999, '邀请码表未就绪');
        }

        $validator = Validator::make($request->all(), [
            'invite_code' => ['required', 'string', 'max:32'],
        ]);
        if ($validator->fails()) {
            return $this->fail(1003, $validator->errors()->first() ?: '参数校验失败');
        }

        $inviteCode = strtoupper(trim((string) $request->input('invite_code', '')));
        if ($inviteCode === '') {
            return $this->fail(1003, '注册码不能为空');
        }

        $invite = $this->resolveValidInviteByCode($inviteCode);
        if (! $invite['ok']) {
            return $this->fail((int) ($invite['code'] ?? 1011), (string) ($invite['message'] ?? '注册码无效或已过期'));
        }

        $row = $invite['invite'];
        if (! is_object($row)) {
            return $this->fail(1011, '注册码无效或已过期');
        }

        return $this->ok('注册码可用', [
            'valid' => true,
            'department' => $this->inviteDeptName((int) ($row->dept_id ?? 0)),
            'position' => $this->invitePositionName((int) ($row->position_id ?? 0)),
            'store' => $this->inviteStoreName((int) ($row->store_id ?? 0)),
            'status' => UserModel::employmentStatusLabel((int) ($row->register_status ?? UserModel::STATUS_ON_JOB)),
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        if (! Schema::hasTable('users')) {
            return $this->fail(1999, '用户表未就绪');
        }

        $validator = Validator::make($request->all(), [
            'account' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6'],
            'remember' => ['nullable', 'boolean'],
        ]);
        if ($validator->fails()) {
            return $this->fail(1003, $validator->errors()->first() ?: '参数校验失败');
        }

        $account = trim((string) $request->input('account', ''));
        $password = (string) $request->input('password', '');
        if ($account === '') {
            return $this->fail(1003, '账号不能为空');
        }

        /** @var UserModel|null $user */
        $user = UserModel::query()
            ->where(function ($q) use ($account) {
                $q->where('account', $account)
                    ->orWhere('real_name', $account);
            })
            ->orderByRaw('case when account = ? then 0 else 1 end', [$account])
            ->first();
        if ($user === null || ! Hash::check($password, (string) $user->password)) {
            return $this->fail(1001, '账号或密码错误');
        }

        if ((int) ($user->status ?? UserModel::STATUS_ON_JOB) !== UserModel::STATUS_ON_JOB) {
            $label = UserModel::employmentStatusLabel((int) ($user->status ?? UserModel::STATUS_ON_JOB));
            return $this->fail(1001, '账号当前状态为「'.$label.'」，暂不可登录');
        }

        $token = $user->createToken('app_login')->plainTextToken;
        $name = trim((string) ($user->real_name ?? '')) ?: trim((string) ($user->account ?? ''));

        return $this->ok('登录成功', [
            'token' => $token,
            'userInfo' => [
                'id' => (string) $user->id,
                'name' => $name,
            ],
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('user_invite_codes')) {
            return $this->fail(1999, '用户表或邀请码表未就绪');
        }

        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:6', 'max:20'],
            'confirmPassword' => ['required', 'string'],
            'invite_code' => ['required', 'string', 'max:32'],
        ]);
        if ($validator->fails()) {
            return $this->fail(1003, $validator->errors()->first() ?: '参数校验失败');
        }

        $phone = trim((string) $request->input('phone', ''));
        $name = trim((string) $request->input('name', ''));
        $password = (string) $request->input('password', '');
        $confirmPassword = (string) $request->input('confirmPassword', '');
        $inviteCode = strtoupper(trim((string) $request->input('invite_code', '')));

        if ($phone === '') {
            return $this->fail(1003, '手机号不能为空');
        }
        if (! preg_match('/^1\d{10}$/', $phone)) {
            return $this->fail(1003, '手机号格式不正确');
        }
        if ($name === '') {
            return $this->fail(1003, '姓名不能为空');
        }
        if (mb_strlen($password) < 6 || mb_strlen($password) > 20) {
            return $this->fail(1004, '密码需为 6-20 位');
        }
        if ($password !== $confirmPassword) {
            return $this->fail(1005, '两次密码不一致');
        }

        if (
            UserModel::query()->where('phone', $phone)->exists()
            || UserModel::query()->where('account', $phone)->exists()
        ) {
            return $this->fail(1002, '手机号已存在');
        }

        $invitePack = $this->resolveValidInviteByCode($inviteCode);
        if (! $invitePack['ok']) {
            return $this->fail((int) ($invitePack['code'] ?? 1011), (string) ($invitePack['message'] ?? '注册码无效或已过期'));
        }
        $invite = $invitePack['invite'];
        if (! is_object($invite)) {
            return $this->fail(1011, '注册码无效或已过期');
        }

        $deptId = (int) ($invite->dept_id ?? 0);
        $positionId = (int) ($invite->position_id ?? 0);
        $storeId = (int) ($invite->store_id ?? 0);
        $status = (int) ($invite->register_status ?? UserModel::STATUS_ON_JOB);
        if (! array_key_exists($status, UserModel::employmentStatusOptions())) {
            $status = UserModel::STATUS_ON_JOB;
        }
        if ($deptId <= 0 || $positionId <= 0 || $storeId <= 0) {
            return $this->fail(1003, '邀请码配置不完整，请联系管理员重新生成');
        }

        if ($deptId > 0 && Schema::hasTable('departments')) {
            $deptExists = DB::table('departments')->where('id', $deptId)->where('status', 1)->exists();
            if (! $deptExists) {
                return $this->fail(1003, '邀请码部门无效，请联系管理员重新生成');
            }
        }
        if ($positionId > 0 && Schema::hasTable('positions')) {
            $positionRow = DB::table('positions')->where('id', $positionId)->where('status', 1)->first(['id', 'dept_id']);
            if ($positionRow === null) {
                return $this->fail(1003, '邀请码职务无效，请联系管理员重新生成');
            }
            if ($deptId > 0 && (int) ($positionRow->dept_id ?? 0) !== $deptId) {
                return $this->fail(1003, '邀请码职务与部门不匹配，请联系管理员重新生成');
            }
        }
        if ($storeId > 0 && Schema::hasTable('stores')) {
            $storeExists = DB::table('stores')->where('id', $storeId)->where('status', 1)->exists();
            if (! $storeExists) {
                return $this->fail(1003, '邀请码门店无效，请联系管理员重新生成');
            }
        }

        $now = time();
        $userId = 0;
        try {
            DB::transaction(function () use (
                &$userId,
                $phone,
                $name,
                $password,
                $status,
                $now,
                $inviteCode,
                $deptId,
                $positionId,
                $storeId
            ) {
                $user = new UserModel;
                $user->account = $phone;
                $user->password = $password;
                $user->real_name = $name;
                $user->phone = $phone;
                $user->status = $status;
                $user->created_at = $now;
                $user->updated_at = $now;
                $user->save();

                $userId = (int) $user->id;

                // 默认绑定员工角色（若存在）
                if (Schema::hasTable('user_roles')) {
                    $employeeRoleId = RoleModel::findIdByCode(RoleModel::CODE_EMPLOYEE);
                    if ($employeeRoleId !== null && $employeeRoleId > 0) {
                        DB::table('user_roles')->insert([
                            'user_id' => $userId,
                            'role_id' => $employeeRoleId,
                        ]);
                    }
                }

                if (($deptId > 0 || $positionId > 0) && (Schema::hasTable('user_departments') || Schema::hasTable('user_positions'))) {
                    $user->syncOrgFromIds($deptId > 0 ? [$deptId] : [], $positionId > 0 ? [$positionId] : []);
                }

                if ($storeId > 0 && Schema::hasTable('user_stores')) {
                    $today = date('Y-m-d');
                    DB::table('user_stores')->insert([
                        'user_id' => $userId,
                        'store_id' => $storeId,
                        'position_id' => $positionId > 0 ? $positionId : 0,
                        'is_main' => 1,
                        'start_date' => $today,
                        'end_date' => '9999-12-31',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $affected = DB::table('user_invite_codes')
                    ->where('code', $inviteCode)
                    ->whereNull('used_at')
                    ->whereNull('used_user_id')
                    ->update([
                        'used_at' => $now,
                        'used_user_id' => $userId,
                        'updated_at' => $now,
                    ]);
                if ($affected !== 1) {
                    throw new \RuntimeException('邀请码已被使用，请刷新后重试');
                }
            });
        } catch (\Throwable $e) {
            return $this->fail(1003, $e->getMessage() ?: '注册失败');
        }

        $user = UserModel::query()->find($userId);
        if (! $user instanceof UserModel) {
            return $this->fail(1999, '注册失败，请稍后重试');
        }

        return $this->ok('注册成功', [
            'userId' => (string) $user->id,
            'phone' => $phone,
            'name' => $name,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return $this->fail(1006, '未登录');
        }

        $token = $user->currentAccessToken();
        if ($token !== null) {
            $token->delete();
        }

        return $this->ok('登出成功', null);
    }

    /**
     * 修改登录密码（小程序）：校验当前密码后更新；当前 Token 可继续使用。
     */
    public function password(Request $request): JsonResponse
    {
        if (! Schema::hasTable('users')) {
            return $this->fail(1999, '用户表未就绪');
        }

        $user = $request->user();
        if (! $user instanceof UserModel) {
            return $this->fail(1006, '未登录');
        }

        $validator = Validator::make($request->all(), [
            'currentPassword' => ['required', 'string'],
            'password' => ['required', 'string'],
            'confirmPassword' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return $this->fail(1003, $validator->errors()->first() ?: '参数校验失败');
        }

        $currentPassword = (string) $request->input('currentPassword', '');
        $password = (string) $request->input('password', '');
        $confirmPassword = (string) $request->input('confirmPassword', '');

        if ($password !== $confirmPassword) {
            return $this->fail(1005, '两次密码不一致');
        }
        if (mb_strlen($password) < 6 || mb_strlen($password) > 20) {
            return $this->fail(1004, '新密码需为 6-20 位');
        }

        if (! Hash::check($currentPassword, (string) $user->password)) {
            return $this->fail(1010, '当前密码不正确');
        }

        $user->password = $password;
        $user->updated_at = time();
        $user->save();

        return $this->ok('密码已更新', null);
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    private function ok(string $message = 'success', ?array $data = []): JsonResponse
    {
        return response()->json([
            'code' => 0,
            'message' => $message,
            'data' => $data,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    private function fail(int $code, string $message, ?array $data = null): JsonResponse
    {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ]);
    }

    /**
     * @return array{ok:bool, code?:int, message?:string, invite?:object}
     */
    private function resolveValidInviteByCode(string $inviteCode): array
    {
        $invite = DB::table('user_invite_codes')
            ->where('code', $inviteCode)
            ->first();
        if ($invite === null) {
            return ['ok' => false, 'code' => 1011, 'message' => '注册码无效或已过期'];
        }
        if (($invite->used_at ?? null) !== null || (int) ($invite->used_user_id ?? 0) > 0) {
            return ['ok' => false, 'code' => 1011, 'message' => '注册码无效或已过期'];
        }

        $expiresAt = $invite->expires_at !== null ? (int) $invite->expires_at : 0;
        if ($expiresAt > 0 && $expiresAt < time()) {
            return ['ok' => false, 'code' => 1011, 'message' => '注册码无效或已过期'];
        }

        return ['ok' => true, 'invite' => $invite];
    }

    private function inviteDeptName(int $deptId): string
    {
        if ($deptId <= 0 || ! Schema::hasTable('departments')) {
            return '—';
        }

        $name = DB::table('departments')->where('id', $deptId)->value('name');

        return trim((string) ($name ?? '')) ?: '—';
    }

    private function invitePositionName(int $positionId): string
    {
        if ($positionId <= 0 || ! Schema::hasTable('positions')) {
            return '—';
        }

        $name = DB::table('positions')->where('id', $positionId)->value('name');

        return trim((string) ($name ?? '')) ?: '—';
    }

    private function inviteStoreName(int $storeId): string
    {
        if ($storeId <= 0 || ! Schema::hasTable('stores')) {
            return '—';
        }

        $name = DB::table('stores')->where('id', $storeId)->value('name');

        return trim((string) ($name ?? '')) ?: '—';
    }
}
