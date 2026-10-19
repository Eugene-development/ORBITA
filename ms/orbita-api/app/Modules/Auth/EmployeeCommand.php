<?php
namespace App\Modules\Auth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
final class EmployeeCommand extends Command {
    protected $signature = 'orbita:employee {email} {--name=} {--role=editor} {--deactivate}';
    protected $description = 'Provision an assigned employee (no public registration); prompts securely for password';
    public function handle(): int {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        if ($this->option('deactivate')) {
            $employee = Employee::query()->where('email', $email)->firstOrFail();
            $employee->update(['is_active' => false]); $this->info('Employee disabled.'); return self::SUCCESS;
        }
        $values = ['email' => $email, 'name' => $this->option('name') ?: $this->ask('Employee name'),
            'role' => $this->option('role'), 'password' => $this->secret('Password (12+ characters, letters and numbers)')];
        $validator = Validator::make($values, ['email' => ['required', 'email', 'max:254'], 'name' => ['required', 'string', 'max:200'],
            'role' => ['required', 'in:admin,editor'], 'password' => ['required', Password::min(12)->letters()->numbers()]]);
        if ($validator->fails()) { $this->error('Invalid employee data.'); return self::FAILURE; }
        Employee::query()->updateOrCreate(['email' => $email], array_merge($validator->validated(), ['is_active' => true]));
        $this->info('Assigned employee saved.'); return self::SUCCESS;
    }
}
