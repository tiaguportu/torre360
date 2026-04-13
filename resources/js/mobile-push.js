import { PushNotifications } from '@capacitor/push-notifications';
import { Capacitor } from '@capacitor/core';

const registerPush = async () => {
    alert('Tentando registrar Push...');
    
    // Só executa se estiver em ambiente nativo (Android/iOS)
    if (Capacitor.getPlatform() === 'web') {
        console.log('Ambiente Web: Notificações Push nativas não disponíveis.');
        return;
    }

    try {
        let permStatus = await PushNotifications.checkPermissions();

        if (permStatus.receive === 'prompt') {
            permStatus = await PushNotifications.requestPermissions();
        }

        if (permStatus.receive !== 'granted') {
            console.warn('Permissão de notificação negada.');
            return;
        }

        // Criar canal de notificação oficial (necessário para Android 8+)
        if (Capacitor.getPlatform() === 'android') {
            await PushNotifications.createChannel({
                id: 'default',
                name: 'Notificações Padrão',
                description: 'Canal para avisos e notificações gerais do sistema',
                importance: 5, // Importância máxima
                visibility: 1, // Visível na tela de bloqueio
                sound: 'default',
                vibration: true,
            });
            console.log('Canal de notificação configurado.');
        }

        await PushNotifications.register();

        PushNotifications.addListener('registration', (token) => {
            console.log('Token FCM recebido:', token.value);
            
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
            .then(response => response.json())
            .then(data => console.log('Registro no backend:', data.message))
            .catch(err => console.error('Erro ao registrar token no backend:', err));
        });

        PushNotifications.addListener('registrationError', (error) => {
            console.error('Erro no registro do Push Capacitor:', error);
        });

        PushNotifications.addListener('pushNotificationReceived', (notification) => {
            console.log('Notificação recebida em foreground:', notification);
        });

        PushNotifications.addListener('pushNotificationActionPerformed', (notification) => {
            console.log('Ação na notificação:', notification);
            
            // Debug visual no celular
            alert('Notificação clicada! Dados: ' + JSON.stringify(notification.notification.data || {}));

            // A estrutura de dados do Capacitor varia, tentamos pegar de ambos os lugares comuns
            const data = notification.notification.data || notification.notification.extras;

            if (data && data.url) {
                alert('Redirecionando para: ' + data.url);
                window.location.href = data.url;
            } else {
                alert('Nenhuma URL encontrada nos dados da notificação.');
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
