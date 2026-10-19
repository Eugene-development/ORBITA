<?php
namespace App\Modules\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

final class AuthController
{
    public function csrf(Request $request): JsonResponse
    {
        return response()->json(['csrf_token' => $request->session()->token()]);
    }
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email', 'max:254'], 'password' => ['required', 'string', 'max:1024']]);
        $credentials['email'] = mb_strtolower(trim($credentials['email']));
        $credentials['is_active'] = true;
        if (! Auth::attempt($credentials)) { throw ValidationException::withMessages(['email' => 'Не удалось войти. Проверьте данные.']); }
        $request->session()->regenerate();
        return $this->me($request);
    }
    public function me(Request $request): JsonResponse
    {
        $employee = $request->user();
        if ($employee && ! $employee->is_active) { Auth::logout(); $request->session()->invalidate(); $employee = null; }
        return response()->json(['user' => $employee, 'capabilities' => ['catalogCrud' => $employee && in_array($employee->role, ['admin', 'editor']), 'manageEmployees' => $employee?->role === 'admin']]);
    }
    public function logout(Request $request): JsonResponse
    {
        Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
        return response()->json(['user' => null]);
    }
    public function forgotPassword(Request $request): JsonResponse
    {
        $input = $request->validate(['email' => ['required', 'email', 'max:254']]);
        // The same response is returned for nonexistent, inactive and registered accounts.
        $employee = Employee::query()->where('email', mb_strtolower(trim($input['email'])))->where('is_active', true)->first();
        if ($employee) { Password::broker('employees')->sendResetLink(['email' => $employee->email]); }
        return response()->json(['message' => 'Если сотрудник зарегистрирован, ссылка для восстановления отправлена.']);
    }
    public function resetPassword(Request $request): JsonResponse
    {
        $input = $request->validate(['token' => ['required', 'string'], 'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->numbers()]]);
        $status = Password::broker('employees')->reset($input, function (Employee $employee, string $password): void {
            if (! $employee->is_active) { throw ValidationException::withMessages(['email' => 'Восстановление недоступно.']); }
            $employee->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
        });
        if ($status !== Password::PASSWORD_RESET) { throw ValidationException::withMessages(['token' => 'Ссылка недействительна или истекла.']); }
        return response()->json(['message' => 'Пароль изменён. Войдите снова.']);
    }
}
