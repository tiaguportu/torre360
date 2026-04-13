<?php
use App\Models\User;
use App\Models\Matricula;
use App\Notifications\DocumentosPendentesNotification;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = User::first();
$matricula = Matricula::first();

if ($user && $matricula) {
    echo "Enviando notificação para o usuário: " . $user->email . "\n";
    $user->notify(new DocumentosPendentesNotification($matricula));
    
    $notif = $user->notifications()->latest()->first();
    if ($notif) {
        echo "Notificação criada no banco! ID: " . $notif->id . "\n";
        echo "Dados: " . json_encode($notif->data) . "\n";
    } else {
        echo "FALHA: Notificação não encontrada no banco.\n";
    }
} else {
    echo "Usuário ou Matrícula não encontrados.\n";
}
