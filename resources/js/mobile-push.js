import { PushNotifications } from '@capacitor/push-notifications';
import { Capacitor } from '@capacitor/core';

const registerPush = async () => {
    // Só executa se estiver em ambiente nativo (Android/iOS)
    if (Capacitor.getPlatform() === 'web') {
        return;
    }

    try {
        let permStatus = await PushNotifications.checkPermissions();

        if (permStatus.receive === 'prompt') {
            permStatus = await PushNotifications.requestPermissions();
        }

        if (permStatus.receive !== 'granted') {
            return;
        }

        // Criar canal de notificação oficial (necessário para Android 8+)
        if (Capacitor.getPlatform() === 'android') {
            await PushNotifications.createChannel({
                id: 'default',
                name: 'Notificações Padrão',
                description: 'Canal para avisos e notificações gerais do sistema',
                importance: 5,
                visibility: 1,
                sound: 'default',
                vibration: true,
            });
        }

        await PushNotifications.register();

        PushNotifications.addListener('registration', (token) => {
            // Envia o token para o backend Laravel
            fetch('/mobile/register-token', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                },
                body: JSON.stringify({
                    token: token.value,
                    platform: Capacitor.getPlatform()
                })
            })
            .catch(err => console.error('Erro ao registrar token no backend:', err));
        });

        PushNotifications.addListener('pushNotificationReceived', (notification) => {
            console.log('Notificação recebida em foreground:', notification);
        });

        PushNotifications.addListener('pushNotificationActionPerformed', (notification) => {
            // A estrutura de dados do Capacitor varia, tentamos pegar de ambos os lugares comuns
            const data = notification.notification.data || notification.notification.extras;

            if (data && data.url) {
                window.location.href = data.url;
            }
        });

    } catch (e) {
        console.error('Falha ao inicializar notificações push:', e);
    }
};

// Se o usuário estiver logado, tenta registrar o push
document.addEventListener('DOMContentLoaded', () => {
    // Verifica se existe o token CSRF (indício de que a página carregou e o usuário pode estar logado)
    const csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (csrfToken) {
        registerPush();
    }
});
