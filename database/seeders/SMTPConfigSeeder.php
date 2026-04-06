<?php

namespace Database\Seeders;

use App\Models\Configuracao;
use Illuminate\Database\Seeder;

class SMTPConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $configs = [
            ['campo' => 'mail_mailer', 'valor' => 'smtp', 'grupo' => 'SMTP', 'ordem' => 10],
            ['campo' => 'mail_host', 'valor' => 'smtp.mailtrap.io', 'grupo' => 'SMTP', 'ordem' => 20],
            ['campo' => 'mail_port', 'valor' => '2525', 'grupo' => 'SMTP', 'ordem' => 30],
            ['campo' => 'mail_username', 'valor' => '', 'grupo' => 'SMTP', 'ordem' => 40],
            ['campo' => 'mail_password', 'valor' => '', 'grupo' => 'SMTP', 'ordem' => 50],
            ['campo' => 'mail_encryption', 'valor' => 'tls', 'grupo' => 'SMTP', 'ordem' => 60],
            ['campo' => 'mail_from_address', 'valor' => 'noreply@torre360.com', 'grupo' => 'SMTP', 'ordem' => 70],
            ['campo' => 'mail_from_name', 'valor' => 'Torre360 Gestão Escolar', 'grupo' => 'SMTP', 'ordem' => 80],
        ];

        foreach ($configs as $config) {
            Configuracao::updateOrCreate(
                ['campo' => $config['campo']],
                $config
            );
        }
    }
}
