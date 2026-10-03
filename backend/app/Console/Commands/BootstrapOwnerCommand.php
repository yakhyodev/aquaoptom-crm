<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BootstrapOwnerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:bootstrap-owner
                            {--name= : Do\'kon egasi ismi}
                            {--email= : Do\'kon egasi emaili}
                            {--phone= : Telefon raqami}
                            {--password= : Xavfsiz parol (agar berilmasa tasodifiy 16 xonali parol generatsiya qilinadi)}
                            {--force : Mavjud egasini hisobga olmasdan yangi owner qo\'shish}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tizimning birinchi do\'kon egasini (OWNER) xavfsiz, default parolsiz yaratish';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('=== AquaOptom CRM: Birinchi do\'kon egasini (OWNER) ro\'yxatdan o\'tkazish ===');

        // Check if an owner already exists
        $existingOwner = User::where('role', 'OWNER')->first();
        if ($existingOwner && ! $this->option('force')) {
            $this->error("Xatolik: Tizimda allaqachon do'kon egasi mavjud: [{$existingOwner->name} - {$existingOwner->email}].");
            $this->comment("Yangi owner qo'shish uchun --force bayrog'idan foydalaning.");

            return self::FAILURE;
        }

        $name = $this->option('name');
        if (empty($name)) {
            $name = $this->input->isInteractive()
                ? $this->ask('Do\'kon egasining to\'liq ismi', 'Do\'kon Egasi')
                : 'Do\'kon Egasi';
        }

        $email = $this->option('email');
        if (empty($email)) {
            $email = $this->input->isInteractive()
                ? $this->ask('Do\'kon egasining emaili', 'owner@aquaoptom.uz')
                : 'owner@aquaoptom.uz';
        }

        $phone = $this->option('phone');
        if (empty($phone) && $this->input->isInteractive() && ! app()->runningUnitTests()) {
            $phone = $this->ask('Telefon raqami (ixtiyoriy, masalan +998901234567)', null);
        }

        $password = $this->option('password');
        $generatedPassword = false;

        if (empty($password)) {
            if ($this->input->isInteractive() && $this->confirm('Parolni qo\'lda kiritasizmi? (No tanlansa, 16 xonali xavfsiz parol generatsiya qilinadi)', false)) {
                $password = $this->secret('Xavfsiz parolni kiriting');
            } else {
                // Generate secure cryptographically strong random password - NEVER a default password
                $password = Str::password(16, true, true, false);
                $generatedPassword = true;
            }
        }

        if (empty($password) || strlen($password) < 8) {
            $this->error('Xatolik: Parol kamida 8 ta belgidan iborat bo\'lishi shart.');

            return self::FAILURE;
        }

        // Check unique email
        $userWithEmail = User::where('email', $email)->first();
        if ($userWithEmail) {
            if ($this->option('force')) {
                $userWithEmail->update([
                    'name' => $name,
                    'password' => Hash::make($password),
                    'role' => 'OWNER',
                    'status' => 'ACTIVE',
                    'is_active' => true,
                    'phone' => $phone,
                ]);
                $owner = $userWithEmail;
                $this->info("Mavjud foydalanuvchi OWNER roliga yangilandi: [{$email}]");
            } else {
                $this->error("Xatolik: [{$email}] emaili bilan foydalanuvchi allaqachon mavjud.");

                return self::FAILURE;
            }
        } else {
            $owner = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => 'OWNER',
                'status' => 'ACTIVE',
                'is_active' => true,
                'phone' => $phone,
            ]);
        }

        $this->newLine();
        $this->info('✔ Do\'kon egasi muvaffaqiyatli yaratildi!');
        $this->table(
            ['ID', 'Ism', 'Email', 'Telefon', 'Rol', 'Holat'],
            [[$owner->id, $owner->name, $owner->email, $owner->phone ?? '-', $owner->role, $owner->status]]
        );

        if ($generatedPassword) {
            $this->warn('****************************************************************');
            $this->warn('DIQQAT: Tizim xavfsiz tasodifiy parol generatsiya qildi:');
            $this->line("   Parol: <fg=green;options=bold>{$password}</>");
            $this->warn("Ushbu parolni saqlab oling va birinchi kirishdan so'ng yangilang!");
            $this->warn('****************************************************************');
        }

        return self::SUCCESS;
    }
}
