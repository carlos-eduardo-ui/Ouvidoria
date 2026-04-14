-- ═══════════════════════════════════════════════════════════════════
-- migration.sql — Migração completa do banco dbouvidoria
-- Ouvidoria Escolar — Grêmio Estudantil
--
-- Banco estava: latin1, senhas em texto puro, constraints erradas
-- Banco ficará: utf8mb4, bcrypt, estrutura profissional
--
-- ATENÇÃO: execute na ordem. Banco deve estar VAZIO.
-- ═══════════════════════════════════════════════════════════════════

USE `dbouvidoria`;

-- ───────────────────────────────────────────────────────────────────
-- 0. Converter charset do banco inteiro para utf8mb4
--    (suporte completo a acentos, emojis e caracteres especiais)
-- ───────────────────────────────────────────────────────────────────
ALTER DATABASE `dbouvidoria`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════
-- 1. NOVA TABELA: tbsetores
--    Órgãos da escola que podem receber manifestações
-- ═══════════════════════════════════════════════════════════════════
DROP TABLE IF EXISTS `tbsetores`;
CREATE TABLE `tbsetores` (
  `IDsetor`    int(11) unsigned NOT NULL AUTO_INCREMENT,
  `nome`       varchar(80)  NOT NULL                    COMMENT 'Nome do setor/órgão',
  `descricao`  varchar(200) DEFAULT NULL                COMMENT 'Descrição opcional',
  `ativo`      tinyint(1)   NOT NULL DEFAULT 1          COMMENT '1=ativo, 0=desativado',
  `criado_em`  datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`IDsetor`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Setores/órgãos da escola que recebem manifestações';

-- Setores iniciais da escola
-- (você pode adicionar mais diretamente por aqui ou pelo painel ADM)
INSERT INTO `tbsetores` (`nome`, `descricao`) VALUES
  ('Direção',          'Direção geral da escola'),
  ('Coordenação',      'Coordenação pedagógica'),
  ('Grêmio Estudantil','Representação dos estudantes'),
  ('Biblioteca',       'Biblioteca e acervo escolar'),
  ('Cozinha',          'Equipe de alimentação e refeitório'),
  ('Corpo Docente',    'Professores e equipe de ensino');

-- ═══════════════════════════════════════════════════════════════════
-- 2. ALTERAR: tbusuarios (alunos / comunidade escolar)
-- ═══════════════════════════════════════════════════════════════════

-- 2a. Converter charset
ALTER TABLE `tbusuarios`
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 2b. Ajustar colunas existentes
ALTER TABLE `tbusuarios`
  MODIFY `nome`       varchar(80)  NOT NULL,
  MODIFY `serie`      int(1)       DEFAULT NULL COMMENT 'Série/ano escolar',
  MODIFY `curso`      varchar(60)  DEFAULT NULL,
  MODIFY `matricula`  varchar(40)  DEFAULT NULL,
  MODIFY `email`      varchar(200) NOT NULL,

  -- CORREÇÃO CRÍTICA: senha de varchar(20) texto puro → varchar(255) para hash bcrypt
  -- bcrypt gera sempre 60 caracteres; 255 é o padrão seguro
  MODIFY `senha`      varchar(255) NOT NULL
    COMMENT 'bcrypt hash — NUNCA armazenar senha em texto puro';

-- 2c. Adicionar colunas novas
ALTER TABLE `tbusuarios`
  ADD COLUMN `ativo`      tinyint(1) NOT NULL DEFAULT 1
    COMMENT '1=ativo, 0=bloqueado'
    AFTER `senha`,

  ADD COLUMN `criado_em`  datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
    AFTER `ativo`;

-- 2d. Índices únicos para evitar duplicatas
ALTER TABLE `tbusuarios`
  ADD UNIQUE KEY `uq_email`     (`email`),
  ADD UNIQUE KEY `uq_matricula` (`matricula`);

-- ═══════════════════════════════════════════════════════════════════
-- 3. ALTERAR: tbadm (Grêmio / administradores do sistema)
-- ═══════════════════════════════════════════════════════════════════

-- 3a. Converter charset
ALTER TABLE `tbadm`
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 3b. Ajustar colunas existentes
ALTER TABLE `tbadm`
  MODIFY `nome`   varchar(80)  NOT NULL,
  MODIFY `email`  varchar(200) NOT NULL,

  -- CORREÇÃO CRÍTICA: mesma mudança de senha
  MODIFY `senha`  varchar(255) NOT NULL
    COMMENT 'bcrypt hash — NUNCA armazenar senha em texto puro';

-- 3c. Adicionar colunas novas
ALTER TABLE `tbadm`
  ADD COLUMN `cargo`      varchar(80)  DEFAULT NULL
    COMMENT 'Cargo no Grêmio (ex: Presidente, Secretário)'
    AFTER `nome`,

  ADD COLUMN `ativo`      tinyint(1) NOT NULL DEFAULT 1
    AFTER `senha`,

  ADD COLUMN `criado_em`  datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
    AFTER `ativo`;

-- 3d. Índice único de e-mail
ALTER TABLE `tbadm`
  ADD UNIQUE KEY `uq_adm_email` (`email`);

-- ═══════════════════════════════════════════════════════════════════
-- 4. ALTERAR: tipos (Denúncia, Reclamação, Sugestão, Elogio…)
-- ═══════════════════════════════════════════════════════════════════
ALTER TABLE `tipos`
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `tipos`
  MODIFY `descricao` varchar(60) NOT NULL;

-- Tipos padrão do sistema
INSERT INTO `tipos` (`descricao`) VALUES
  ('Denúncia'),
  ('Reclamação'),
  ('Sugestão'),
  ('Elogio'),
  ('Solicitação');

-- ═══════════════════════════════════════════════════════════════════
-- 5. ALTERAR: tbmanifest
--    Esta é a tabela mais crítica — tem FKs que precisam ser
--    removidas antes de alterar, e recriadas depois
-- ═══════════════════════════════════════════════════════════════════

-- 5a. Remover FKs antigas (necessário para modificar colunas)
ALTER TABLE `tbmanifest`
  DROP FOREIGN KEY `idusu`,
  DROP FOREIGN KEY `idadm`,
  DROP FOREIGN KEY `idtipo`;

-- 5b. Converter charset
ALTER TABLE `tbmanifest`
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 5c. Ajustar colunas existentes
ALTER TABLE `tbmanifest`
  -- CORREÇÃO: IDusu agora permite NULL → manifestações anônimas
  MODIFY `IDusu`  int(11) unsigned DEFAULT NULL
    COMMENT 'NULL = manifestação anônima',

  -- CORREÇÃO: IDadm agora permite NULL → admin atribuído depois
  MODIFY `IDadm`  int(11) unsigned DEFAULT NULL
    COMMENT 'NULL = sem atendente atribuído ainda',

  -- CORREÇÃO: STATUS de char(2) obscuro → ENUM legível
  MODIFY `STATUS` enum(
    'Aberta',
    'Em análise',
    'Respondida',
    'Encerrada'
  ) NOT NULL DEFAULT 'Aberta'
    COMMENT 'Status atual da manifestação',

  MODIFY `manifest` text    NOT NULL,
  MODIFY `contato`  varchar(150) DEFAULT NULL
    COMMENT 'Contato opcional (só para identificadas)';

-- 5d. Adicionar colunas novas
ALTER TABLE `tbmanifest`
  -- Protocolo público de rastreamento (ex: OUV-2025-00042)
  ADD COLUMN `protocolo`  varchar(20) NOT NULL
    COMMENT 'Número de protocolo público para rastreamento'
    AFTER `IDmanifest`,

  -- Setor/órgão alvo da manifestação
  ADD COLUMN `IDsetor`    int(11) unsigned DEFAULT NULL
    COMMENT 'Órgão da escola ao qual a manifestação se refere'
    AFTER `IDtipo`,

  -- Flag de anonimato (o coração do sistema de privacidade)
  ADD COLUMN `anonimo`    tinyint(1) NOT NULL DEFAULT 0
    COMMENT '1 = anônima (IDusu gravado como NULL)'
    AFTER `IDsetor`,

  ADD COLUMN `criado_em`  datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
    AFTER `contato`,

  ADD COLUMN `atualizado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP
    AFTER `criado_em`;

-- 5e. Índices para performance e unicidade
ALTER TABLE `tbmanifest`
  ADD UNIQUE KEY `uq_protocolo` (`protocolo`),
  ADD        KEY `idx_status`   (`STATUS`),
  ADD        KEY `idx_setor`    (`IDsetor`),
  ADD        KEY `idx_criado`   (`criado_em`);

-- 5f. Recriar FKs com nomes novos e ON DELETE correto
ALTER TABLE `tbmanifest`
  ADD CONSTRAINT `fk_manifest_usu`
    FOREIGN KEY (`IDusu`)
    REFERENCES `tbusuarios` (`IDusu`)
    ON DELETE SET NULL,

  ADD CONSTRAINT `fk_manifest_adm`
    FOREIGN KEY (`IDadm`)
    REFERENCES `tbadm` (`IDadm`)
    ON DELETE SET NULL,

  ADD CONSTRAINT `fk_manifest_tipo`
    FOREIGN KEY (`IDtipo`)
    REFERENCES `tipos` (`IDtipo`),

  ADD CONSTRAINT `fk_manifest_setor`
    FOREIGN KEY (`IDsetor`)
    REFERENCES `tbsetores` (`IDsetor`)
    ON DELETE SET NULL;

-- ═══════════════════════════════════════════════════════════════════
-- 6. ADMIN PADRÃO (Grêmio Estudantil)
--
--    A senha é gerada pelo script gerar_hash.php
--    Não coloque senha real aqui — este arquivo vai para o Git
--
--    COMO USAR:
--      1. Rode: php db/gerar_hash.php SuaSenhaAqui
--      2. Copie o hash gerado
--      3. Substitua o placeholder abaixo e execute
-- ═══════════════════════════════════════════════════════════════════
-- INSERT INTO `tbadm` (`nome`, `cargo`, `email`, `senha`) VALUES
--   ('Grêmio Estudantil', 'Administrador Geral', 'gremio@escola.edu.br',
--    '$2y$12$HASH_GERADO_PELO_SCRIPT_AQUI');

-- ═══════════════════════════════════════════════════════════════════
-- 7. VIEW útil para o painel do Grêmio
--    Junta as tabelas mais usadas em uma consulta só
-- ═══════════════════════════════════════════════════════════════════
CREATE OR REPLACE VIEW `v_manifestacoes` AS
SELECT
  m.IDmanifest,
  m.protocolo,
  m.anonimo,

  -- Dados do autor (NULL se anônima)
  CASE WHEN m.anonimo = 1 THEN 'Anônimo'
       ELSE u.nome END                    AS autor_nome,
  CASE WHEN m.anonimo = 1 THEN NULL
       ELSE u.email END                   AS autor_email,
  CASE WHEN m.anonimo = 1 THEN NULL
       ELSE u.serie END                   AS autor_serie,
  CASE WHEN m.anonimo = 1 THEN NULL
       ELSE u.curso END                   AS autor_curso,

  -- Manifestação
  t.descricao                             AS tipo,
  s.nome                                  AS setor,
  m.manifest,
  m.STATUS,
  m.feedback,
  m.contato,

  -- Atendimento
  a.nome                                  AS atendente,

  -- Datas
  m.criado_em,
  m.atualizado_em

FROM `tbmanifest` m
LEFT JOIN `tbusuarios`  u ON m.IDusu   = u.IDusu
LEFT JOIN `tbadm`       a ON m.IDadm   = a.IDadm
LEFT JOIN `tipos`       t ON m.IDtipo  = t.IDtipo
LEFT JOIN `tbsetores`   s ON m.IDsetor = s.IDsetor;
