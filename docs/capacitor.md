# Capacitor — Torre 360

## O que foi configurado

- **App ID:** `com.escolatorredemarfim.torre360`
- **App Name:** Torre 360
- **Plataformas:** Android + iOS
- **Modo:** WebView remota (aponta para o servidor de produção)
- **URL:** `https://torre360.escolatorredemarfim.com.br/admin`

> **Importante:** O URL acima deve ser atualizado para o teu domínio real em produção (`capacitor.config.json` → `server.url`).

## Arquivos gerados

| Pasta/Arquivo | Descrição |
|---|---|
| `capacitor.config.json` | Configuração central do Capacitor |
| `android/` | Projeto Android Studio nativo (ignorado no .gitignore) |
| `ios/` | Projeto Xcode nativo (ignorado no .gitignore) |

## Pré-requisitos para build

### Android
- [Android Studio](https://developer.android.com/studio) instalado
- SDK Android 33+ configurado
- `JAVA_HOME` a apontar para JDK 17+

### iOS (apenas em macOS)
- Xcode 15+
- CocoaPods: `sudo gem install cocoapods`
- Depois: `cd ios/App && pod install`

## Fluxo de trabalho

```bash
# Abrir no Android Studio
npx cap open android

# Abrir no Xcode (macOS)
npx cap open ios

# Sincronizar após alterar capacitor.config.json
npx cap sync
```

## Como o app funciona

O app está em **modo WebView remota**:
- Não é necessário fazer build do frontend localmente
- O app carrega diretamente o URL configurado
- Qualquer atualização no servidor é imediatamente refletida no app

> **Atenção:** O servidor precisa ter **HTTPS com certificado SSL válido**. Android e iOS bloqueiam HTTP por padrão.

## Plugins instalados

| Plugin | Função |
|---|---|
| `@capacitor/splash-screen` | Splash screen de arranque |
| `@capacitor/status-bar` | Controlo da barra de status nativa |

## Próximos passos

1. Confirmar/atualizar o URL em `capacitor.config.json`
2. Instalar Android Studio → `npx cap open android`
3. Criar ícone (512×512 px) e splash screen (2732×2732 px)
4. Testar no emulador antes de publicar
