<?php
session_start(); // Inicia a sessão do usuário para manter dados de login e permissões entre páginas.
require_once("conexao.php"); // Inclui o arquivo que faz a conexão com o banco de dados usando PDO.

/* =========================================================
   SEGURANÇA — VERIFICAÇÃO DE ACESSO
   ========================================================= */
// Verifica se o usuário está logado e se é do tipo profissional.
// Se não estiver logado ou for cliente, redireciona para a página de login.
if (!isset($_SESSION["usuario_id"]) || $_SESSION["usuario_tipo"] !== "profissional") {
    header("Location: login.php"); // Envia o usuário para a tela de login.
    exit(); // Interrompe a execução do restante do código por segurança.
}
$profissional_id = (int)$_SESSION["usuario_id"]; // Armazena o ID da profissional logada, convertido para número inteiro por segurança.

/* =========================================================
   DEFINIÇÃO DE FILTROS DE BUSCA
   ========================================================= */
$mes = $_GET["mes"] ?? date("m"); // Pega o mês enviado pela URL ou usa o mês atual como padrão.
$ano = $_GET["ano"] ?? date("Y"); // Pega o ano enviado pela URL ou usa o ano atual como padrão.
$filtro_status = $_GET["status"] ?? "todos"; // Pega o status selecionado ou mostra todos por padrão.
$mes = str_pad(min(max((int)$mes, 1), 12), 2, "0", STR_PAD_LEFT); // Garante que o mês seja entre 01 e 12 com 2 dígitos.
$ano = min(max((int)$ano, 2020), date("Y") + 1); // Garante que o ano seja válido entre 2020 e o ano seguinte ao atual.

/* =========================================================
   CONSULTA AO BANCO — CARREGAR REGISTROS
   ========================================================= */
try { // Inicia bloco de proteção contra erros na consulta.
    $where_status = ""; // Variável que vai guardar o filtro de status se for selecionado.
    $parametros = [":pid" => $profissional_id, ":ano" => $ano, ":mes" => $mes]; // Valores que serão enviados para a consulta.
    if ($filtro_status !== "todos") { // Verifica se o usuário escolheu um status específico.
        $where_status = " AND a.status = :status "; // Adiciona a condição de status na consulta.
        $parametros[":status"] = $filtro_status; // Inclui o valor do status nos parâmetros.
    }
    // Consulta principal: busca os agendamentos do mês e ano selecionados, com dados do cliente e do serviço.
    $consulta = $conexao->prepare("
        SELECT a.id, a.data_agendamento, a.horario, a.status, a.preco_final, a.observacoes,
               c.nome AS nome_cliente, c.telefone AS telefone_cliente,
               s.nome_servico, s.tipo_servico
        FROM agendamentos a
        LEFT JOIN usuarios c ON a.cliente_id = c.id
        LEFT JOIN servicos s ON a.servico_id = s.id
        WHERE a.profissional_id = :pid AND YEAR(a.data_agendamento) = :ano AND MONTH(a.data_agendamento) = :mes $where_status
        ORDER BY a.data_agendamento DESC, a.horario DESC
    ");
    $consulta->execute($parametros); // Executa a consulta com os valores preparados.
    $atendimentos = $consulta->fetchAll(PDO::FETCH_ASSOC); // Guarda todos os resultados em formato de lista.
} catch (PDOException $e) { // Se houver algum erro na consulta...
    $erro = "Erro ao carregar histórico."; // Mensagem explicativa.
    $atendimentos = []; // Define lista vazia para não travar a página.
}

/* =========================================================
   DADOS PARA NAVEGAÇÃO ENTRE OS MESES
   ========================================================= */
// Lista com os nomes dos meses em português para exibição.
$nomes_meses = ["01"=>"Janeiro","02"=>"Fevereiro","03"=>"Março","04"=>"Abril","05"=>"Maio","06"=>"Junho","07"=>"Julho","08"=>"Agosto","09"=>"Setembro","10"=>"Outubro","11"=>"Novembro","12"=>"Dezembro"];
$nome_mes = $nomes_meses[$mes] ?? ""; // Converte o número do mês para o nome por extenso.
$mes_anterior = $mes == 1 ? 12 : $mes - 1; // Define o mês anterior (se for janeiro, volta para dezembro).
$ano_anterior = $mes == 1 ? $ano - 1 : $ano; // Define o ano correto do mês anterior.
$proximo_mes = $mes == 12 ? 1 : $mes + 1; // Define o próximo mês (se for dezembro, avança para janeiro).
$ano_proximo = $mes == 12 ? $ano + 1 : $ano; // Define o ano correto do próximo mês.

/* =========================================================
   CÁLCULOS PARA O RESUMO ESTATÍSTICO
   ========================================================= */
$total = count($atendimentos); // Conta quantos agendamentos foram encontrados no total.
$concluidos = count(array_filter($atendimentos, fn($a) => ($a["status"]??"") === "concluido")); // Conta quantos foram concluídos.
$pendentes = count(array_filter($atendimentos, fn($a) => ($a["status"]??"") === "pendente")); // Conta quantos ainda estão pendentes.
$cancelados = count(array_filter($atendimentos, fn($a) => ($a["status"]??"") === "cancelado")); // Conta quantos foram cancelados.
$alterados = count(array_filter($atendimentos, fn($a) => ($a["status"]??"") === "alterado")); // Conta quantos foram alterados.

/* =========================================================
   FUNÇÕES AUXILIARES DE FORMATAÇÃO
   ========================================================= */
function formatarDinheiro($valor) { // Função que formata valores para o padrão brasileiro de moeda.
    if (!$valor) return "---"; // Se o valor estiver vazio, mostra traços ao invés de zero.
    return "R$ " . number_format($valor, 2, ",", "."); // Formata com símbolo R$, vírgula e ponto corretos.
}
function etiquetaStatus($status) { // Função que retorna o status formatado com cor e estilo.
    return match($status) {
        "concluido" => "<span class='etiqueta-status etiqueta-concluido'>Concluído</span>", // Status finalizado.
        "pendente" => "<span class='etiqueta-status etiqueta-pendente'>Pendente</span>", // Ainda vai acontecer.
        "cancelado" => "<span class='etiqueta-status etiqueta-cancelado'>Cancelado</span>", // Foi cancelado.
        "alterado" => "<span class='etiqueta-status etiqueta-alterado'>Alterado</span>", // Teve dados alterados.
        default => "<span class='etiqueta-status etiqueta-pendente'>Desconhecido</span>" // Se o status não for reconhecido.
    };
}
?>
<!DOCTYPE html> <!-- Define o tipo de documento como HTML5 -->
<html lang="pt-BR"> <!-- Define o idioma da página como português do Brasil -->
<head>
    <meta charset="UTF-8"> <!-- Define a codificação de caracteres para aceitar acentuação e símbolos -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Faz a página se adaptar em celulares -->
    <title>Histórico de Atendimentos</title> <!-- Título que aparece na aba do navegador -->
    <link rel="stylesheet" href="style.css"> <!-- Importa o arquivo de estilos com as cores e formatação -->
</head>
<body>
<aside class="sidebar"> <!-- Área lateral com o menu de navegação -->
    <div class="logo"> <!-- Área com o nome do sistema e especialidade da profissional -->
        <h1>Estética</h1> <!-- Nome exibido no topo do menu -->
        <?php $especialidade = htmlspecialchars($_SESSION["especialidade"] ?? "Profissional"); ?><p><?= $especialidade ?></p> <!-- Mostra a especialidade da profissional logada -->
    </div>
    <nav class="menu"> <!-- Conjunto de links de navegação entre as páginas -->
        <a href="painel_profissional.php">Início</a> <!-- Link para a página inicial do painel -->
        <a href="minha_agenda.php">Minha Agenda</a> <!-- Link para o calendário de agendamentos -->
        <a href="meus_servicos.php">Meus Serviços</a> <!-- Link para gerenciar serviços oferecidos -->
        <a href="historico_atendimentos.php" class="ativo">Histórico</a> <!-- Link para esta página (destacada como ativa) -->
        <div class="separador"></div> <!-- Linha separadora visual -->
        <a href="meu_perfil.php">Meu Perfil</a> <!-- Link para editar dados da profissional -->
    </nav>
    <a href="logout.php" class="btn-sair">Sair</a> <!-- Botão para encerrar a sessão e sair do sistema -->
</aside>
<main class="main"> <!-- Área principal de conteúdo da página -->
    <section class="topo"><div><h2>Histórico de Atendimentos</h2><p>Registros e situação de todos os seus agendamentos</p></div></section> <!-- Título e descrição da página -->
    <div class="navegacao-mes"> <!-- Área com botões para avançar e voltar entre os meses -->
        <a href="?mes=<?= $mes_anterior ?>&ano=<?= $ano_anterior ?>&status=<?= $filtro_status ?>">◀</a> <!-- Botão para o mês anterior -->
        <h3><?= $nome_mes ?> de <?= $ano ?></h3> <!-- Exibe o mês e ano atual selecionado -->
        <a href="?mes=<?= $proximo_mes ?>&ano=<?= $ano_proximo ?>&status=<?= $filtro_status ?>">▶</a> <!-- Botão para o próximo mês -->
    </div>
    <div class="resumo-cards"> <!-- Caixas com o resumo estatístico do mês -->
        <div class="card"><div class="card-valor"><?= $total ?></div><div class="card-texto">Total</div></div> <!-- Total de agendamentos -->
        <div class="card"><div class="card-valor" style="color:var(--cor-sucesso);"><?= $concluidos ?></div><div class="card-texto">Concluídos</div></div> <!-- Quantos já foram concluídos -->
        <div class="card"><div class="card-valor" style="color:var(--cor-pendente);"><?= $pendentes ?></div><div class="card-texto">Pendentes</div></div> <!-- Quantos ainda estão pendentes -->
        <div class="card"><div class="card-valor" style="color:var(--cor-cancelado);"><?= $cancelados ?></div><div class="card-texto">Cancelados</div></div> <!-- Quantos foram cancelados -->
        <div class="card"><div class="card-valor" style="color:var(--cor-alterado);"><?= $alterados ?></div><div class="card-texto">Alterados</div></div> <!-- Quantos foram alterados -->
    </div>
    <div class="filtros"> <!-- Botões para filtrar a lista pelo status do agendamento -->
        <a href="?mes=<?= $mes ?>&ano=<?= $ano ?>&status=todos" class="filtro-btn todos <?= $filtro_status==='todos'?'ativo':'' ?>">Todos (<?= $total ?>)</a> <!-- Mostra todos os registros -->
        <a href="?mes=<?= $mes ?>&ano=<?= $ano ?>&status=concluido" class="filtro-btn concluido <?= $filtro_status==='concluido'?'ativo':'' ?>">Concluídos (<?= $concluidos ?>)</a> <!-- Mostra apenas os concluídos -->
        <a href="?mes=<?= $mes ?>&ano=<?= $ano ?>&status=pendente" class="filtro-btn pendente <?= $filtro_status==='pendente'?'ativo':'' ?>">Pendentes (<?= $pendentes ?>)</a> <!-- Mostra apenas os pendentes -->
        <a href="?mes=<?= $mes ?>&ano=<?= $ano ?>&status=cancelado" class="filtro-btn cancelado <?= $filtro_status==='cancelado'?'ativo':'' ?>">Cancelados (<?= $cancelados ?>)</a> <!-- Mostra apenas os cancelados -->
        <a href="?mes=<?= $mes ?>&ano=<?= $ano ?>&status=alterado" class="filtro-btn alterado <?= $filtro_status==='alterado'?'ativo':'' ?>">Alterados (<?= $alterados ?>)</a> <!-- Mostra apenas os alterados -->
    </div>
    <div class="tabela-container"> <!-- Área que contém a lista com os registros -->
        <?php if (empty($atendimentos)): ?> <!-- Verifica se não há nenhum registro encontrado -->
            <div class="sem-registros"><h3>Nenhum registro encontrado</h3><p>Não há agendamentos com o filtro selecionado neste mês.</p></div> <!-- Mensagem quando não há dados -->
        <?php else: ?> <!-- Se houver registros, monta a tabela -->
            <table><thead><tr><th class="data-col">Data</th><th>Horário</th><th>Cliente</th><th>Serviço</th><th>Valor</th><th>Status</th></tr></thead><tbody> <!-- Cabeçalho da tabela com os nomes das colunas -->
            <?php foreach ($atendimentos as $a): ?> <!-- Repetição que percorre cada agendamento encontrado -->
                <?php 
                    $data_formatada = date("d/m/Y", strtotime($a["data_agendamento"])); // Converte a data do banco para o formato dia/mês/ano.
                    $horario = substr($a["horario"] ?? "--:--", 0, 5); // Pega apenas as 5 primeiras letras do horário (hora:minuto).
                ?>
            <tr> <!-- Linha com os dados de um agendamento -->
                <td class="data-col"><?= $data_formatada ?></td> <!-- Data do agendamento formatada -->
                <td><strong><?= $horario ?></strong></td> <!-- Horário do atendimento -->
                <td><?= htmlspecialchars($a["nome_cliente"] ?? "Cliente removido") ?> <!-- Nome do cliente ou mensagem se não existir mais -->
                    <?php if (!empty($a["telefone_cliente"])): ?><br><small><?= htmlspecialchars($a["telefone_cliente"]) ?></small><?php endif; ?></td> <!-- Telefone do cliente se estiver cadastrado -->
                <td><?= htmlspecialchars($a["nome_servico"] ?? "Serviço não informado") ?> <!-- Nome do serviço prestado -->
                    <?php if (!empty($a["tipo_servico"]) && $a["tipo_servico"] !== "completo"): ?><br><small><?= htmlspecialchars(ucfirst($a["tipo_servico"])) ?></small><?php endif; ?></td> <!-- Tipo do serviço se não for completo -->
                <td><?= formatarDinheiro($a["preco_final"] ?? null) ?></td> <!-- Valor final formatado em moeda -->
                <td><?= etiquetaStatus($a["status"] ?? "pendente") ?></td> <!-- Situação do agendamento com etiqueta colorida -->
            </tr>
            <?php endforeach; ?> <!-- Fim da repetição -->
            </tbody></table> <!-- Fim da tabela -->
        <?php endif; ?> <!-- Fim da verificação se há registros -->
    </div>
</main>
</body>
</html>