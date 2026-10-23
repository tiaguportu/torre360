@if ($oficial->temCopiaAssinada())
    <div class="status-card success" style="margin-top: 18px;">
        <div class="status-icon">🔏</div>
        <div class="status-text">
            <h2>Cópia com assinatura digital</h2>
            <p>
                A escola guardou uma cópia deste documento assinada digitalmente
                @if ($oficial->assinado_em)
                    em <strong>{{ $oficial->assinado_em->format('d/m/Y H:i') }}</strong>
                @endif
                ({{ $oficial->assinatura_nivel?->getLabel() ?? 'assinatura digital' }}).
                Peça o arquivo assinado à secretaria e confira a assinatura no Verificador de Conformidade do ITI
                (validar.iti.gov.br). Esta página confirma que o documento foi emitido pela escola; ela não valida o certificado.
            </p>
        </div>
    </div>
@endif
