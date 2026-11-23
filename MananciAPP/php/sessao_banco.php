<?php
// Sessões do PHP guardadas no Postgres (tabela id_sessao_php, seção 70 do
// bancosupabase.md) em vez de arquivo no /tmp do container. O disco do
// Render some a cada deploy, reinício e hibernação do plano free — e com ele
// todas as sessões: todo mundo era deslogado. Com mais de uma instância,
// cada uma teria as suas.
//
// Carregado antes de QUALQUER página pelo auto_prepend_file do Dockerfile
// (várias páginas chamam session_start() antes de incluir php/conexao.php,
// então o handler precisa estar registrado antes delas). Não roda no XAMPP
// local, que não usa o Dockerfile — lá as sessões continuam em arquivo.
//
// Só liga com SESSOES_NO_BANCO=1 E com a tabela existindo: com a variável
// ligada antes de rodar o SQL, continua em arquivo (e avisa no log) em vez
// de deslogar todo mundo a cada página.
//
// Sem trava por sessão (a de arquivo trava): duas requisições ao mesmo
// tempo podem gravar por cima uma da outra — a última vence. O app não
// depende disso: login, logout e revogação valem pelo ID_SESSAO no banco.

if (getenv('SESSOES_NO_BANCO') === '1' && session_status() === PHP_SESSION_NONE && !class_exists('SiteSessaoBanco', false)) {

    class SiteSessaoBanco implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface {
        private $pdo = null;
        private $lido = array();
        private $idade = array();
        private $falhou = array();

        // Mesma conexão persistente de php/conexao.php (mesmo DSN, usuário e
        // opções): o PDO reaproveita a conexão do processo, sem abrir outra
        // no pooler do Supabase.
        public static function conectar() {
            $host = trim((string) getenv('DB_HOST'));
            if ($host === '') {
                return null;
            }
            $dsn = 'pgsql:host=' . $host . ';port=' . (int) (getenv('DB_PORT') ?: 5432) . ';dbname=' . trim((string) (getenv('DB_NAME') ?: 'postgres'));
            if (stripos($host, 'supabase.co') !== false) {
                $dsn .= ';sslmode=require';
            }
            try {
                $pdo = new PDO($dsn, trim((string) getenv('DB_USER')), trim((string) getenv('DB_PASS')), array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
                    PDO::ATTR_PERSISTENT => true,
                ));
                // Uma ida ao banco por consulta, como em php/conexao.php.
                $pdo->setAttribute(PDO::PGSQL_ATTR_DISABLE_PREPARES, true);
                return $pdo;
            } catch (\Throwable $e) {
                return null;
            }
        }

        private function validade() {
            return max(60, (int) ini_get('session.gc_maxlifetime'));
        }

        #[\ReturnTypeWillChange]
        public function open($caminho, $nome) {
            $this->pdo = self::conectar();
            return $this->pdo !== null;
        }

        #[\ReturnTypeWillChange]
        public function close() {
            return true;
        }

        #[\ReturnTypeWillChange]
        public function read($id) {
            // validateId() já leu a mesma linha nesta requisição: usa o que
            // veio, sem uma segunda consulta igual (antes eram duas).
            if (!array_key_exists($id, $this->lido)) {
                $achou = $this->carregar($id);
                if ($achou === null) {
                    // Banco não respondeu: false faz o session_start() falhar
                    // nesta requisição, em vez de começar uma sessão vazia que
                    // depois seria gravada por cima da verdadeira (a pessoa
                    // era deslogada num soluço do pooler).
                    $this->falhou[$id] = true;
                    return false;
                }
                if (!$achou) {
                    $this->lido[$id] = '';
                }
            }
            return $this->lido[$id];
        }

        // Dados e idade da sessão numa consulta só. true = achou, false = não
        // existe ou venceu, null = o banco não respondeu (não é "não existe").
        private function carregar($id) {
            $stmt = $this->pdo->prepare("SELECT dados, EXTRACT(EPOCH FROM (NOW() - atualizado_em))::int AS idade FROM id_sessao_php WHERE id = ? AND atualizado_em > NOW() - (? * INTERVAL '1 second')");
            if (!$stmt || !$stmt->execute(array((string) $id, $this->validade()))) {
                error_log('Sessões: o banco não respondeu ao ler a sessão.');
                return null;
            }
            $linha = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$linha) {
                return false;
            }
            $this->lido[$id] = (string) $linha['dados'];
            $this->idade[$id] = (int) $linha['idade'];
            return true;
        }

        #[\ReturnTypeWillChange]
        public function write($id, $dados) {
            if (!empty($this->falhou[$id])) {
                return true; // nunca grava por cima sem ter lido a sessão de verdade
            }
            $stmt = $this->pdo->prepare("
                INSERT INTO id_sessao_php (id, dados, atualizado_em) VALUES (?, ?, NOW())
                ON CONFLICT (id) DO UPDATE SET dados = EXCLUDED.dados, atualizado_em = NOW()
            ");
            return $stmt && $stmt->execute(array((string) $id, (string) $dados));
        }

        #[\ReturnTypeWillChange]
        public function destroy($id) {
            $stmt = $this->pdo->prepare("DELETE FROM id_sessao_php WHERE id = ?");
            if ($stmt) {
                $stmt->execute(array((string) $id));
            }
            unset($this->lido[$id], $this->idade[$id]);
            return true;
        }

        #[\ReturnTypeWillChange]
        public function gc($max_lifetime) {
            $stmt = $this->pdo->prepare("DELETE FROM id_sessao_php WHERE atualizado_em < NOW() - (? * INTERVAL '1 second')");
            if (!$stmt || !$stmt->execute(array(max(60, (int) $max_lifetime)))) {
                return false;
            }
            return $stmt->rowCount();
        }

        // session.use_strict_mode: id que o próprio servidor não criou (ou já
        // vencido) é recusado e o PHP gera outro.
        #[\ReturnTypeWillChange]
        public function validateId($id) {
            $achou = $this->carregar($id);
            if ($achou === null) {
                // Banco não respondeu: aceita o id (não gera outro, o que
                // deslogaria a pessoa); o read() logo depois decide.
                return true;
            }
            return $achou;
        }

        // Sessão lida e não alterada: só renova a validade, e só se a última
        // renovação tiver mais de 5 min (antes era uma escrita em toda
        // requisição, inclusive nos pollings). A validade é de horas, então
        // 5 min a menos não mudam nada.
        #[\ReturnTypeWillChange]
        public function updateTimestamp($id, $dados) {
            if (isset($this->idade[$id]) && $this->idade[$id] < 300) {
                return true;
            }
            $stmt = $this->pdo->prepare("UPDATE id_sessao_php SET atualizado_em = NOW() WHERE id = ?");
            return $stmt && $stmt->execute(array((string) $id));
        }
    }

    // A tabela existe? Conferido no banco no máximo a cada 10 min por
    // container (marca num arquivo do /tmp) — não vale uma ida ao banco a
    // mais em toda página só para isso.
    $sessao_marca = sys_get_temp_dir() . '/mananciapp_sessao_banco_ok';
    $sessao_tabela_ok = is_file($sessao_marca) && (time() - (int) @filemtime($sessao_marca)) < 600;
    if (!$sessao_tabela_ok) {
        $sessao_pdo = SiteSessaoBanco::conectar();
        $sessao_res = $sessao_pdo ? $sessao_pdo->query("SELECT to_regclass('public.id_sessao_php') IS NOT NULL") : false;
        $sessao_tabela_ok = $sessao_res && $sessao_res->fetchColumn();
        if ($sessao_tabela_ok) {
            @touch($sessao_marca);
        } else {
            error_log('Sessões: SESSOES_NO_BANCO=1, mas a tabela id_sessao_php não existe (seção 70 do bancosupabase.md). Usando arquivo.');
        }
        unset($sessao_pdo, $sessao_res);
    }

    if ($sessao_tabela_ok) {
        session_set_save_handler(new SiteSessaoBanco(), true);
    }
    unset($sessao_marca, $sessao_tabela_ok);
}
