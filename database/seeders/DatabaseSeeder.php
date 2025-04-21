<?php

namespace Database\Seeders;

use App\Models\LearningProfile;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name'     => 'Test User',
            'email'    => 'test@example.com',
            'password' => bcrypt('password'), // password
            'bio'      => 'A passionate learner.',
            'age'      => 25,
            'gender'   => 'other',
            'language' => 'en',
        ]);

        LearningProfile::create([
            'user_id'             => 1,
            'primary_skill'       => 'Next.js',
            'skill_level'         => 70,
            'secondary_skills'    => ['JavaScript', 'React'],
            'goals'               => 'Build a personal project website using Next.js',
            'learning_style'      => 'Visual',
            'daily_learning_time' => '1 hour/day',
            'preferred_resources' => ['Video', 'Article'],
            'interests'           => ['Technology', 'Business'],
        ]);

    }
}
