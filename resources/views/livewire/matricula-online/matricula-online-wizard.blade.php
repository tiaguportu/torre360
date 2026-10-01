<div class="py-10 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto">

    {{-- Título e Boas-Vindas --}}
    <div class="text-center mb-8">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-50 px-3.5 py-1 text-xs font-semibold text-primary-700 ring-1 ring-inset ring-primary-700/20 mb-3">
            ✨ Processo 100% Digital e Seguro
        </span>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
            Matrícula Online para Novos Alunos
        </h1>
        <p class="mt-2 text-sm text-slate-600 max-w-xl mx-auto">
            Garanta a vaga do seu filho em poucos minutos. Escolha o curso, informe os dados, anexe os documentos e assine o contrato digitalmente.
        </p>
    </div>

    {{-- Stepper de Progresso --}}
    <div class="mb-10">
        <div class="flex items-center justify-between relative">
            <div class="absolute left-0 top-1/2 -translate-y-1/2 h-1 bg-slate-200 w-full z-0"></div>
            <div class="absolute left-0 top-1/2 -translate-y-1/2 h-1 bg-primary-600 transition-all duration-300 z-0"
                 style="width: {{ (($passoAtual - 1) / 4) * 100 }}%;"></div>

            @php
                $passos = [
                    1 => ['label' => 'Curso & Turma', 'icon' => '1'],
                    2 => ['label' => 'Estudante', 'icon' => '2'],
                    3 => ['label' => 'Responsável', 'icon' => '3'],
                    4 => ['label' => 'Documentos', 'icon' => '4'],
                    5 => ['label' => 'Contrato & Aceite', 'icon' => '5'],
                ];
            @endphp

            @foreach ($passos as $numero => $p)
                @php
                    $isCompleto = $passoAtual > $numero;
                    $isAtivo = $passoAtual === $numero;
                @endphp
                <div class="relative z-10 flex flex-col items-center">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-xs transition shadow-sm
                        {{ $isCompleto ? 'bg-emerald-600 text-white' : ($isAtivo ? 'bg-primary-600 text-white ring-4 ring-primary-100' : 'bg-white border-2 border-slate-300 text-slate-500') }}">
                        @if ($isCompleto)
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                        @else
                            {{ $numero }}
                        @endif
                    </div>
                    <span class="text-[11px] font-semibold mt-2 hidden sm:block {{ $isAtivo ? 'text-primary-700' : ($isCompleto ? 'text-slate-800' : 'text-slate-400') }}">
                        {{ $p['label'] }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Alerta de Erro Geral --}}
    @if ($mensagemErro)
        <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-start gap-3 shadow-sm">
            <svg class="w-5 h-5 text-rose-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <div>
                <strong class="font-bold">Atenção ao concluir:</strong>
                <p class="mt-0.5">{{ $mensagemErro }}</p>
            </div>
        </div>
    @endif

    {{-- Card do Formulário --}}
    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-md p-6 sm:p-8">

        {{-- PASSO 1: CURSO, SÉRIE E TURMA --}}
        @if ($passoAtual === 1)
            <div class="space-y-6">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Passo 1: Selecione a Turma Pretendida</h2>
                    <p class="text-xs text-slate-500 mt-1">Escolha a unidade, etapa de ensino e verifique a disponibilidade de vagas em tempo real.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">Unidade Escolar *</label>
                        <select wire:model.live="unidade_id" class="w-full rounded-xl border-slate-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                            @foreach ($this->unidades as $u)
                                <option value="{{ $u->id }}">{{ $u->nome }}</option>
                            @endforeach
                        </select>
                        @error('unidade_id') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">Curso / Nível *</label>
                        <select wire:model.live="curso_id" class="w-full rounded-xl border-slate-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">Selecione o Curso...</option>
                            @foreach ($this->cursos as $c)
                                <option value="{{ $c->id }}">{{ $c->nome_externo ?: $c->nome_interno }}</option>
                            @endforeach
                        </select>
                        @error('curso_id') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                @if ($curso_id)
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">Série / Ano *</label>
                        <select wire:model.live="serie_id" class="w-full rounded-xl border-slate-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">Selecione a Série / Ano...</option>
                            @foreach ($this->series as $s)
                                <option value="{{ $s->id }}">{{ $s->nome }}</option>
                            @endforeach
                        </select>
                        @error('serie_id') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                @endif

                @if ($serie_id)
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-2">Turmas e Horários Disponíveis *</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @forelse ($this->turmas as $t)
                                @php
                                    $ocupadas = $t->matriculas->count();
                                    $max = $t->vagas_maximas ?: 30;
                                    $restantes = max(0, $max - $ocupadas);
                                    $lotada = $restantes <= 0;
                                    $selecionada = ($turma_id === $t->id);
                                @endphp
                                <label class="relative flex flex-col p-4 rounded-xl border cursor-pointer transition
                                    {{ $selecionada ? 'border-primary-600 bg-primary-50/50 ring-2 ring-primary-500' : 'border-slate-200 bg-slate-50/50 hover:bg-white' }}
                                    {{ $lotada ? 'opacity-60 cursor-not-allowed bg-slate-100' : '' }}">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <input type="radio" wire:model.live="turma_id" value="{{ $t->id }}" {{ $lotada ? 'disabled' : '' }}
                                                   class="text-primary-600 focus:ring-primary-500">
                                            <span class="font-bold text-sm text-slate-900">{{ $t->nome }}</span>
                                        </div>
                                        <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full {{ $lotada ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-800' }}">
                                            {{ $lotada ? 'Lotada' : "{$restantes} vagas livres" }}
                                        </span>
                                    </div>
                                    <div class="mt-2 text-xs text-slate-500">
                                        Turno: <strong>{{ $t->turno?->nome ?? 'Regular' }}</strong>
                                    </div>
                                </label>
                            @empty
                                <div class="col-span-2 p-4 text-center text-xs text-slate-500 rounded-xl bg-slate-50 border border-slate-200">
                                    Nenhuma turma encontrada para esta série. Entre em contato com a secretaria.
                                </div>
                            @endforelse
                        </div>
                        @error('turma_id') <span class="text-xs text-rose-600 mt-1.5 block">{{ $message }}</span> @enderror
                    </div>
                @endif
            </div>
        @endif

        {{-- PASSO 2: DADOS DO ALUNO --}}
        @if ($passoAtual === 2)
            <div class="space-y-6">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Passo 2: Identificação do Estudante</h2>
                    <p class="text-xs text-slate-500 mt-1">Preencha os dados do aluno que irá ingressar na instituição.</p>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Nome Completo do Aluno *</label>
                        <input type="text" wire:model="aluno_nome" placeholder="Ex: Lucas Henrique Santos"
                               class="w-full rounded-xl border-slate-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                        @error('aluno_nome') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">CPF do Aluno (se possuir)</label>
                            <input type="text" wire:model="aluno_cpf" placeholder="000.000.000-00" maxlength="14"
                                   class="w-full rounded-xl border-slate-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                            @error('aluno_cpf') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Data de Nascimento *</label>
                            <input type="date" wire:model="aluno_data_nascimento"
                                   class="w-full rounded-xl border-slate-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                            @error('aluno_data_nascimento') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Sexo *</label>
                            <select wire:model="aluno_sexo" class="w-full rounded-xl border-slate-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                                <option value="masculino">Masculino</option>
                                <option value="feminino">Feminino</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Cor / Raça (Censo Escolar)</label>
                            <select wire:model="aluno_cor_raca" class="w-full rounded-xl border-slate-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                                <option value="Branca">Branca</option>
                                <option value="Parda">Parda</option>
                                <option value="Preta">Preta</option>
                                <option value="Amarela">Amarela</option>
                                <option value="Indígena">Indígena</option>
                                <option value="Não declarada">Não declarada</option>
                            </select>
                        </div>
                    </div>

                    <div class="pt-2">
                        <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 bg-slate-50 cursor-pointer">
                            <input type="checkbox" wire:model="aluno_necessidades_especiais"
                                   class="rounded text-primary-600 focus:ring-primary-500">
                            <span class="text-xs text-slate-700 font-medium">
                                O estudante possui necessidades educacionais especiais ou laudo médico?
                            </span>
                        </label>
                    </div>
                </div>
            </div>
        @endif

        {{-- PASSO 3: RESPONSÁVEL LEGAL & ENDEREÇO --}}
        @if ($passoAtual === 3)
            <div class="space-y-6">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Passo 3: Responsável Legal e Financeiro</h2>
                    <p class="text-xs text-slate-500 mt-1">Informe os dados do responsável que assinará o contrato e receberá o acesso ao Portal.</p>
                </div>

                <div class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nome Completo do Responsável *</label>
                            <input type="text" wire:model="responsavel_nome" placeholder="Ex: Maria Aparecida Santos"
                                   class="w-full rounded-xl border-slate-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                            @error('responsavel_nome') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">CPF do Responsável *</label>
                            <input type="text" wire:model="responsavel_cpf" placeholder="000.000.000-00" maxlength="14"
                                   class="w-full rounded-xl border-slate-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                            @error('responsavel_cpf') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">WhatsApp / Telefone *</label>
                            <input type="text" wire:model="responsavel_telefone" placeholder="(11) 99999-9999"
                                   class="w-full rounded-xl border-slate-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                            @error('responsavel_telefone') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">E-mail para Acesso ao Portal *</label>
                            <input type="email" wire:model="responsavel_email" placeholder="responsavel@email.com"
                                   class="w-full rounded-xl border-slate-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                            @error('responsavel_email') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Parentesco / Vínculo *</label>
                            <select wire:model="responsavel_tipo_vinculo_id" class="w-full rounded-xl border-slate-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                                @foreach ($this->tiposVinculo as $tv)
                                    <option value="{{ $tv->id }}">{{ $tv->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="border-t border-slate-200 pt-4 mt-4">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">Endereço Residencial</h3>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">CEP *</label>
                                <div class="flex gap-2">
                                    <input type="text" wire:model="cep" wire:blur="buscarCep" placeholder="00000-000" maxlength="9"
                                           class="w-full rounded-xl border-slate-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                                    <button type="button" wire:click="buscarCep"
                                            class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl border border-slate-300 transition">
                                        Buscar
                                    </button>
                                </div>
                                @error('cep') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Rua / Logradouro *</label>
                                <input type="text" wire:model="logradouro" placeholder="Ex: Av. Paulista"
                                       class="w-full rounded-xl border-slate-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                                @error('logradouro') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Número *</label>
                                <input type="text" wire:model="numero" placeholder="123"
                                       class="w-full rounded-xl border-slate-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                                @error('numero') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Complemento</label>
                                <input type="text" wire:model="complemento" placeholder="Apto 42"
                                       class="w-full rounded-xl border-slate-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Bairro *</label>
                                <input type="text" wire:model="bairro" placeholder="Bairro"
                                       class="w-full rounded-xl border-slate-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                                @error('bairro') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Cidade / UF *</label>
                                <div class="flex gap-2">
                                    <input type="text" wire:model="cidade_nome" placeholder="Cidade"
                                           class="w-3/4 rounded-xl border-slate-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                                    <input type="text" wire:model="estado_sigla" placeholder="SP" maxlength="2"
                                           class="w-1/4 rounded-xl border-slate-300 text-sm uppercase text-center focus:border-primary-500 focus:ring-primary-500">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- PASSO 4: DOCUMENTOS DIGITAIS --}}
        @if ($passoAtual === 4)
            <div class="space-y-6">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Passo 4: Documentação Digital</h2>
                    <p class="text-xs text-slate-500 mt-1">Anexe fotos nítidas ou arquivos em PDF dos documentos solicitados. Você também pode complementar posteriormente pelo Portal da Família.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/50">
                        <label class="block text-xs font-bold text-slate-800 mb-1">Certidão de Nascimento / RG do Aluno</label>
                        <p class="text-[11px] text-slate-500 mb-3">Foto ou PDF legível do documento oficial da criança.</p>
                        <input type="file" wire:model="documento_aluno" accept="image/*,application/pdf"
                               class="text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100">
                        @error('documento_aluno') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/50">
                        <label class="block text-xs font-bold text-slate-800 mb-1">Documento com Foto do Responsável</label>
                        <p class="text-[11px] text-slate-500 mb-3">RG ou CNH com CPF do responsável que assina o contrato.</p>
                        <input type="file" wire:model="documento_responsavel" accept="image/*,application/pdf"
                               class="text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100">
                        @error('documento_responsavel') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/50">
                        <label class="block text-xs font-bold text-slate-800 mb-1">Comprovante de Residência</label>
                        <p class="text-[11px] text-slate-500 mb-3">Conta recente de água, luz ou gás em nome da família.</p>
                        <input type="file" wire:model="comprovante_residencia" accept="image/*,application/pdf"
                               class="text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100">
                        @error('comprovante_residencia') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/50">
                        <label class="block text-xs font-bold text-slate-800 mb-1">Histórico / Declaração Escolar Anterior</label>
                        <p class="text-[11px] text-slate-500 mb-3">Opcional: documento da escola anterior em caso de transferência.</p>
                        <input type="file" wire:model="historico_anterior" accept="image/*,application/pdf"
                               class="text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100">
                    </div>
                </div>
            </div>
        @endif

        {{-- PASSO 5: CONTRATO & ASSINATURA ELETRÔNICA --}}
        @if ($passoAtual === 5)
            <div class="space-y-6">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Passo 5: Contrato Escolar e Assinatura Eletrônica</h2>
                    <p class="text-xs text-slate-500 mt-1">Revise o resumo dos dados e declare o aceite dos termos para formalizar a matrícula.</p>
                </div>

                {{-- Resumo da Matrícula --}}
                <div class="rounded-xl bg-slate-50 p-4 border border-slate-200 text-xs text-slate-700 space-y-2">
                    <div class="flex justify-between border-b border-slate-200 pb-2">
                        <span class="font-semibold text-slate-500">Estudante:</span>
                        <strong class="text-slate-900">{{ $aluno_nome }} (Nasc: {{ date('d/m/Y', strtotime($aluno_data_nascimento)) }})</strong>
                    </div>
                    <div class="flex justify-between border-b border-slate-200 pb-2">
                        <span class="font-semibold text-slate-500">Turma / Turno:</span>
                        <strong class="text-slate-900">{{ $this->turmaSelecionada?->nome }} &middot; Turno {{ $this->turmaSelecionada?->turno?->nome }}</strong>
                    </div>
                    <div class="flex justify-between border-b border-slate-200 pb-2">
                        <span class="font-semibold text-slate-500">Responsável Financeiro:</span>
                        <strong class="text-slate-900">{{ $responsavel_nome }} (CPF: {{ $responsavel_cpf }})</strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-semibold text-slate-500">E-mail de Notificação:</span>
                        <strong class="text-slate-900">{{ $responsavel_email }}</strong>
                    </div>
                </div>

                {{-- Minuta do Contrato em Caixa de Rolagem --}}
                <div class="rounded-xl border border-slate-300 bg-white p-4 h-52 overflow-y-auto text-xs text-slate-600 space-y-3 leading-relaxed shadow-inner">
                    <p class="font-bold text-center text-slate-900 uppercase">Instrumento Particular de Prestação de Serviços Educacionais</p>
                    <p><strong>CLÁUSULA PRIMEIRA — DO OBJETO:</strong> O presente contrato tem por objeto a prestação de serviços educacionais pela CONTRATADA em favor do(a) estudante <strong>{{ $aluno_nome }}</strong>, devidamente qualificado(a), para o ano letivo de {{ date('Y') }}.</p>
                    <p><strong>CLÁUSULA SEGUNDA — DA ASSINATURA ELETRÔNICA:</strong> As partes reconhecem como válida, vinculante e com plena eficácia probatória a manifestação eletrônica de vontade expressada por meio desta plataforma web, com registro de endereço IP, carimbo de data/hora e hash de integridade, conforme o Art. 10, § 2º da Medida Provisória nº 2.200-2/2001 e Lei Federal nº 14.063/2020.</p>
                    <p><strong>CLÁUSULA TERCEIRA — DA LEI GERAL DE PROTEÇÃO DE DADOS (LGPD):</strong> O CONTRATANTE expressamente autoriza a CONTRATADA a coletar, tratar e armazenar os dados pessoais cadastrais e sensíveis exclusivamente para o cumprimento das obrigações acadêmicas, pedagógicas e regulatórias do Ministério da Educação (MEC) e censo escolar.</p>
                </div>

                {{-- Checkboxes de Aceite Obrigatórios --}}
                <div class="space-y-3 pt-2">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" wire:model="aceite_contrato"
                               class="rounded text-primary-600 focus:ring-primary-500 mt-0.5">
                        <span class="text-xs text-slate-800 leading-normal">
                            Li e concordo expressamente com os termos do <strong>Contrato de Prestação de Serviços Educacionais</strong> acima. *
                        </span>
                    </label>
                    @error('aceite_contrato') <span class="text-xs text-rose-600 block pl-6">{{ $message }}</span> @enderror

                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" wire:model="aceite_lgpd"
                               class="rounded text-primary-600 focus:ring-primary-500 mt-0.5">
                        <span class="text-xs text-slate-800 leading-normal">
                            Autorizo o tratamento de dados pessoais para fins pedagógicos e cadastrais conforme a <strong>Lei Geral de Proteção de Dados (LGPD)</strong>. *
                        </span>
                    </label>
                    @error('aceite_lgpd') <span class="text-xs text-rose-600 block pl-6">{{ $message }}</span> @enderror

                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" wire:model="aceite_regimento"
                               class="rounded text-primary-600 focus:ring-primary-500 mt-0.5">
                        <span class="text-xs text-slate-800 leading-normal">
                            Declaro estar ciente das normas regimentais, código de conduta e calendário letivo da instituição. *
                        </span>
                    </label>
                    @error('aceite_regimento') <span class="text-xs text-rose-600 block pl-6">{{ $message }}</span> @enderror
                </div>
            </div>
        @endif

        {{-- Barra de Navegação entre Passos --}}
        <div class="mt-8 pt-6 border-t border-slate-200 flex items-center justify-between">
            @if ($passoAtual > 1)
                <button type="button" wire:click="voltarPasso"
                        class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl border border-slate-300 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 transition shadow-sm">
                    &larr; Voltar
                </button>
            @else
                <div></div>
            @endif

            @if ($passoAtual < 5)
                <button type="button" wire:click="avancarPasso"
                        class="inline-flex items-center gap-1.5 px-6 py-2.5 rounded-xl bg-primary-600 text-xs font-semibold text-white hover:bg-primary-500 transition shadow-sm">
                    Avançar &rarr;
                </button>
            @else
                <button type="button" wire:click="finalizarMatricula"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 px-8 py-3 rounded-xl bg-emerald-600 text-xs font-bold text-white hover:bg-emerald-500 transition shadow-md disabled:opacity-50">
                    <span wire:loading.remove wire:target="finalizarMatricula">
                        ✓ Concluir e Assinar Matrícula Online
                    </span>
                    <span wire:loading wire:target="finalizarMatricula" class="flex items-center gap-1.5">
                        <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                        Processando Matrícula...
                    </span>
                </button>
            @endif
        </div>

    </div>

</div>
