<?php
session_start(); // Inicia a sessão para reconhecer o usuário logado.
require_once("conexao.php"); // Inclui o arquivo que faz a conexão com o banco de dados.

/* =========================================================
   SEGURANÇA — VERIFICAÇÃO DE ACESSO
   ========================================================= */
// Verifica se o usuário está logado e se é do tipo profissional.
if (!isset($_SESSION["usuario_id"]) || $_SESSION["usuario_tipo"] !== "profissional") {
    header("Location: login.php"); // Redireciona para a página de login se não tiver permissão.
    exit(); // Interrompe a execução do restante do código.
}
$profissional_id = (int)$_SESSION["usuario_id"]; // Armazena o ID da profissional logada em formato numérico.

/* =========================================================
   CARREGAR DADOS ATUAIS DO PERFIL
   ========================================================= */
try { // Inicia bloco protegido contra erros na consulta.
    $consulta = $conexao->prepare("
        SELECT id, nome, email, telefone, especialidade
        FROM usuarios
        WHERE id = :id
        LIMIT 1
    "); // Prepara consulta para buscar apenas os dados necessários da profissional logada.
    $consulta->execute([":id" => $profissional_id]); // Executa a consulta passando o ID da profissional.
    $usuario = $consulta->fetch(PDO::FETCH_ASSOC); // Guarda os dados encontrados em formato de lista.
} catch (PDOException $e) { // Se houver erro na consulta...
    $mensagem_erro = "Erro ao carregar dados."; // Define mensagem de aviso.
    $usuario = []; // Define lista vazia para não travar a página.
}

/* =========================================================
   SALVAR ALTERAÇÕES NO PERFIL
   ========================================================= */
$mensagem_sucesso = ""; // Variável que guarda mensagem de sucesso.
$mensagem_erro = ""; // Variável que guarda mensagem de erro.

// Verifica se o formulário foi enviado com a ação de salvar.
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["acao"]) && $_POST["acao"] === "salvar") {
    try { // Inicia bloco protegido contra erros de validação e salvamento.
        $nome = trim($_POST["nome"] ?? ""); // Pega o nome e remove espaços em branco desnecessários.
        $telefone = trim($_POST["telefone"] ?? ""); // Pega o telefone e remove espaços em branco.
        $especialidade = trim($_POST["especialidade"] ?? ""); // Pega a especialidade selecionada.
        $senha_atual = trim($_POST["senha_atual"] ?? ""); // Pega a senha atual informada.
        $nova_senha = trim($_POST["nova_senha"] ?? ""); // Pega a nova senha se informada.
        $confirma_senha = trim($_POST["confirma_senha"] ?? ""); // Pega a confirmação da nova senha.

        if (!$nome || !$especialidade) { // Verifica se os campos obrigatórios foram preenchidos.
            throw new Exception("Nome e especialidade são obrigatórios."); // Exibe mensagem se faltar.
        }

        // Se a profissional informou uma nova senha...
        if ($nova_senha) {
            if (strlen($nova_senha) < 6) { // Verifica se a nova senha tem pelo menos 6 caracteres.
                throw new Exception("A nova senha deve ter pelo menos 6 caracteres.");
            }
            if ($nova_senha !== $confirma_senha) { // Compara se a senha nova é igual à confirmação.
                throw new Exception("A confirmação da senha não confere.");
            }

            // Busca a senha atual salva no banco para comparação.
            $verifica = $conexao->prepare("SELECT senha FROM usuarios WHERE id = :id LIMIT 1");
            $verifica->execute([":id" => $profissional_id]);
            $hash_atual = $verifica->fetchColumn(); // Pega o código criptografado da senha atual.

            if (!password_verify($senha_atual, $hash_atual)) { // Compara a senha digitada com a salva.
                throw new Exception("Senha atual incorreta.");
            }

            $hash_nova = password_hash($nova_senha, PASSWORD_DEFAULT); // Criptografa a nova senha.

            // Atualiza todos os dados incluindo a nova senha.
            $comando = $conexao->prepare("
                UPDATE usuarios 
                SET nome = :nome, telefone = :tel, especialidade = :esp, senha = :senha
                WHERE id = :id
            ");
            $comando->execute([
                ":nome" => $nome, ":tel" => $telefone, ":esp" => $especialidade,
                ":senha" => $hash_nova, ":id" => $profissional_id
            ]);
        } else {
            // Se não informou senha nova, atualiza apenas os dados pessoais.
            $comando = $conexao->prepare("
                UPDATE usuarios 
                SET nome = :nome, telefone = :tel, especialidade = :esp
                WHERE id = :id
            ");
            $comando->execute([
                ":nome" => $nome, ":tel" => $telefone, ":esp" => $especialidade, ":id" => $profissional_id
            ]);
        }

        // Atualiza os dados na sessão para refletir imediatamente as alterações.
        $_SESSION["usuario_nome"] = $nome;
        $_SESSION["especialidade"] = $especialidade;

        $mensagem_sucesso = "✅ Perfil atualizado com sucesso!"; // Define mensagem de sucesso.

        // Recarrega os dados atualizados para exibir na página.
        $consulta->execute([":id" => $profissional_id]);
        $usuario = $consulta->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) { // Se qualquer erro ocorrer durante o salvamento...
        $mensagem_erro = "erro " . $e->getMessage(); // Guarda a mensagem de erro.
    }
}
?>
<!DOCTYPE html> <!-- Define o tipo de documento como HTML5 -->
<html lang="pt-BR"> <!-- Define o idioma da página como português do Brasil -->
<head>
    <meta charset="UTF-8"> <!-- Define a codificação para aceitar acentuação e símbolos -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Faz a página se adaptar a celulares e telas menores -->
    <title>Meu Perfil</title> <!-- Título que aparece na aba do navegador -->
    <link rel="stylesheet" href="style.css"> <!-- Carrega o arquivo de estilos com as cores e formatação -->
</head>
<body>
<aside class="sidebar"> <!-- Área lateral com o menu de navegação -->
    <div class="logo"> <!-- Área com nome do sistema e especialidade da profissional -->
        <h1>Estética</h1> <!-- Nome exibido no topo do menu -->
        <p><?= htmlspecialchars($_SESSION["especialidade"] ?? "Profissional") ?></p> <!-- Exibe a especialidade da profissional logada -->
    </div>
    <nav class="menu"> <!-- Conjunto de links de navegação entre as páginas -->
        <a href="painel_profissional.php">Início</a> <!-- Link para a página inicial do painel -->
        <a href="minha_agenda.php">Minha Agenda</a> <!-- Link para o calendário de agendamentos -->
        <a href="meus_servicos.php">Meus Serviços</a> <!-- Link para gerenciar serviços oferecidos -->
        <a href="historico_atendimentos.php">Histórico</a> <!-- Link para o histórico de atendimentos -->
        <div class="separador"></div> <!-- Linha separadora visual -->
        <a href="meu_perfil.php" class="ativo">Meu Perfil</a> <!-- Link para esta página (destacada como ativa) -->
    </nav>
    <a href="logout.php" class="btn-sair">Sair</a> <!-- Botão para encerrar a sessão e sair do sistema -->
</aside>
<main class="main"> <!-- Área principal de conteúdo da página -->
    <section class="topo"> <!-- Cabeçalho da página com título e explicação -->
        <div>
            <h2>Meu Perfil</h2> <!-- Título da página -->
            <p>Atualize seus dados e altere sua senha quando precisar</p> <!-- Instrução de uso -->
        </div>
    </section>

    <!-- Exibe mensagem de sucesso se o salvamento funcionou -->
    <?php if ($mensagem_sucesso): ?><div class="alerta alerta-sucesso"><?= htmlspecialchars($mensagem_sucesso) ?></div><?php endif; ?>
    <!-- Exibe mensagem de erro se algo deu errado -->
    <?php if ($mensagem_erro): ?><div class="alerta alerta-erro"><?= htmlspecialchars($mensagem_erro) ?></div><?php endif; ?>

    <div class="perfil-container"> <!-- Caixa que envolve todo o formulário -->
        <form method="POST"> <!-- Formulário que envia os dados para a própria página -->
            <input type="hidden" name="acao" value="salvar"> <!-- Campo oculto que identifica a ação de salvar -->
            <div class="form-grid"> <!-- Grade que organiza os campos do formulário -->

                <div class="form-grupo"> <!-- Campo para nome completo -->
                    <label>Nome Completo *</label> <!-- Rótulo do campo com aviso de obrigatório -->
                    <input type="text" name="nome" required value="<?= htmlspecialchars($usuario["nome"] ?? "") ?>"> <!-- Caixa de texto preenchida com o nome atual -->
                </div>

                <div class="form-grupo"> <!-- Campo de e-mail (somente leitura) -->
                    <label>E-mail</label> <!-- Rótulo do campo -->
                    <input type="email" name="email" readonly value="<?= htmlspecialchars($usuario["email"] ?? "") ?>" style="background:var(--cor-clara); opacity:0.8;"> <!-- Exibe o e-mail sem permitir alteração -->
                    <small>E-mail não pode ser alterado</small> <!-- Explicação para o usuário -->
                </div>

                <div class="form-grupo"> <!-- Campo de telefone -->
                    <label>Telefone</label> <!-- Rótulo do campo -->
                    <input type="tel" name="telefone" value="<?= htmlspecialchars($usuario["telefone"] ?? "") ?>" placeholder="(00) 00000-0000"> <!-- Caixa de texto com formato de exemplo -->
                </div>

                <div class="form-grupo"> <!-- Campo de especialidade em formato de lista -->
                    <label>Especialidade *</label> <!-- Rótulo do campo com aviso de obrigatório -->
                    <select name="especialidade" required> <!-- Lista de opções -->
                        <option value="">Selecione...</option> <!-- Opção padrão -->
                        <option value="lash" <?= ($usuario["especialidade"] ?? "") === "lash" ? "selected" : "" ?>>Cílios</option> <!-- Opção Cílios marcada se for a atual -->
                        <option value="nail" <?= ($usuario["especialidade"] ?? "") === "nail" ? "selected" : "" ?>>Unhas</option> <!-- Opção Unhas marcada se for a atual -->
                        <option value="eyebrow" <?= ($usuario["especialidade"] ?? "") === "eyebrow" ? "selected" : "" ?>>Sobrancelhas</option> <!-- Opção Sobrancelhas marcada se for a atual -->
                    </select>
                </div>

                <div class="form-grupo linha-completa"> <!-- Área separada para alteração de senha -->
                    <hr style="border:none; border-top:1px solid var(--cor-borda); margin:10px 0;"> <!-- Linha visual separadora -->
                    <h4 style="color:var(--cor-primaria); margin-bottom:15px;">Alterar Senha (deixe em branco para manter a atual)</h4> <!-- Título e instrução sobre senha -->
                </div>

                <div class="form-grupo"> <!-- Campo para senha atual -->
                    <label>Senha Atual</label> <!-- Rótulo do campo -->
                    <input type="password" name="senha_atual" placeholder="Digite sua senha atual"> <!-- Caixa para digitar a senha atual -->
                </div>

                <div class="form-grupo"> <!-- Campo para nova senha -->
                    <label>Nova Senha</label> <!-- Rótulo do campo -->
                    <input type="password" name="nova_senha" placeholder="Mínimo 6 caracteres"> <!-- Caixa para digitar a nova senha -->
                </div>

                <div class="form-grupo"> <!-- Campo de confirmação de nova senha -->
                    <label>Confirmar Nova Senha</label> <!-- Rótulo do campo -->
                    <input type="password" name="confirma_senha" placeholder="Repita a nova senha"> <!-- Caixa para repetir a nova senha -->
                </div>
            </div>

            <div style="margin-top:25px;"> <!-- Área com botões de ação -->
                <a href="painel_profissional.php" class="btn" style="background:#eee; color:#444;">← Voltar</a> <!-- Botão para retornar ao painel sem salvar -->
                <button type="submit" class="btn btn-primario">Salvar Alterações</button> <!-- Botão que envia o formulário para salvar -->
            </div>
        </form>
    </div>
</main>
</body>
</html>