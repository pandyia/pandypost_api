<?php

namespace Database\Seeders;

use App\Models\ScheduledPost;

use App\Models\User;
use Illuminate\Database\Seeder;

class ScheduledPostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(['email' => 'admin@teste.com'], ['name' => 'Admin', 'password' => bcrypt('password')]);

        ScheduledPost::create([
            'user_id' => $user->id,
            'platform' => 'youtube', // Coloquei youtube para casar com sua Service
            'media_path' => 'video.mp4', // Simulação do arquivo em storage/app/video.mp4
            'title' => 'Meu Vídeo de Teste',
            'caption' => 'Teste scheduler funcional',
            'scheduled_at' => now()->subMinute(), // Define para 1 minuto atrás para o cron pegar agora
            'status' => 'pending',
        ]);
    }
}