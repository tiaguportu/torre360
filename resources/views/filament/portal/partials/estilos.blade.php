<style>
    .pf-stack { display: flex; flex-direction: column; gap: 1.25rem; }
    .pf-muted { opacity: .7; font-size: .8125rem; }
    .pf-box { border: 1px solid rgba(128, 128, 128, .28); border-radius: .75rem; padding: 1rem; }
    .pf-box h3 { font-weight: 600; margin-bottom: .5rem; }
    .pf-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(9.5rem, 1fr)); gap: .75rem; }
    .pf-card { border: 1px solid rgba(128, 128, 128, .28); border-radius: .75rem; padding: .75rem 1rem; }
    .pf-card .pf-valor { font-size: 1.5rem; font-weight: 700; line-height: 1.2; }
    .pf-card .pf-rotulo { font-size: .75rem; opacity: .7; }
    .pf-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .pf-tabela { width: 100%; border-collapse: collapse; font-size: .875rem; }
    .pf-tabela th, .pf-tabela td { padding: .5rem .625rem; text-align: left; border-bottom: 1px solid rgba(128, 128, 128, .2); white-space: nowrap; }
    .pf-tabela th { font-size: .75rem; text-transform: uppercase; letter-spacing: .03em; opacity: .75; }
    .pf-tabela td.pf-num, .pf-tabela th.pf-num { text-align: center; }
    .pf-tabela td.pf-wrap { white-space: normal; min-width: 10rem; }
    .pf-tabela tbody tr:last-child td { border-bottom: 0; }
    .pf-tabela td:first-child, .pf-tabela th:first-child { position: sticky; left: 0; background: #fff; }
    .dark .pf-tabela td:first-child, .dark .pf-tabela th:first-child { background: #18181b; }
    .pf-baixo { color: #dc2626; font-weight: 600; }
    .pf-ok { color: #16a34a; font-weight: 600; }
    .pf-aviso { border: 1px solid #f59e0b; background: rgba(245, 158, 11, .12); border-radius: .75rem; padding: .75rem 1rem; font-size: .875rem; }
    .pf-vazio { border: 1px dashed rgba(128, 128, 128, .4); border-radius: .75rem; padding: 1.5rem; text-align: center; opacity: .8; }
    .pf-semana { display: grid; grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr)); gap: .75rem; }
    .pf-dia { border: 1px solid rgba(128, 128, 128, .28); border-radius: .75rem; padding: .75rem; display: flex; flex-direction: column; gap: .5rem; }
    .pf-dia.pf-hoje { border-color: #3b82f6; box-shadow: 0 0 0 1px #3b82f6; }
    .pf-dia h4 { font-weight: 600; font-size: .875rem; }
    .pf-aula { border-left: 3px solid #3b82f6; padding: .25rem 0 .25rem .5rem; font-size: .8125rem; }
    .pf-aula strong { display: block; }
    .pf-aula .pf-hora { font-variant-numeric: tabular-nums; opacity: .75; }
    .pf-nl { background: rgba(239, 68, 68, .12); color: #b91c1c; border-radius: .5rem; padding: .375rem .5rem; font-size: .8125rem; }
    .pf-barra { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; justify-content: space-between; }
    .pf-barra .pf-acoes { display: flex; flex-wrap: wrap; gap: .5rem; }
    .pf-seletor { max-width: 34rem; width: 100%; }
    @media (max-width: 640px) {
        .pf-semana { grid-template-columns: 1fr; }
        .pf-card .pf-valor { font-size: 1.25rem; }
    }
</style>
