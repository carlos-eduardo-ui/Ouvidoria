-- --------------------------------------------------------
-- Servidor:                     127.0.0.1
-- Versão do servidor:           10.4.32-MariaDB - mariadb.org binary distribution
-- OS do Servidor:               Win64
-- HeidiSQL Versão:              12.17.0.7270
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Copiando estrutura do banco de dados para dbouvidoria
CREATE DATABASE IF NOT EXISTS `dbouvidoria` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;
USE `dbouvidoria`;

-- Copiando estrutura para tabela dbouvidoria.log_acesso
CREATE TABLE IF NOT EXISTS `log_acesso` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) unsigned DEFAULT NULL COMMENT 'NULL = ação anônima',
  `acao` varchar(100) NOT NULL COMMENT 'Ex: manifestacao:42, login, logout',
  `ip` varchar(45) DEFAULT NULL COMMENT 'IPv4 ou IPv6 do dispositivo',
  `user_agent` varchar(255) DEFAULT NULL COMMENT 'Navegador e sistema operacional',
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_acao` (`acao`),
  KEY `idx_criado` (`criado_em`),
  CONSTRAINT `fk_log_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `tbusuarios` (`IDusu`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Auditoria de ações realizadas no sistema';

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela dbouvidoria.password_resets
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(200) NOT NULL,
  `token` varchar(100) NOT NULL,
  `expira_em` datetime NOT NULL,
  `usado` tinyint(1) NOT NULL DEFAULT 0,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_token` (`token`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tokens temporários para recuperação de senha';

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela dbouvidoria.tbadm
CREATE TABLE IF NOT EXISTS `tbadm` (
  `IDadm` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(80) NOT NULL,
  `cargo` varchar(80) DEFAULT NULL COMMENT 'Cargo no Grêmio (ex: Presidente, Secretário)',
  `email` varchar(200) NOT NULL,
  `senha` varchar(255) NOT NULL COMMENT 'bcrypt hash — NUNCA armazenar senha em texto puro',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`IDadm`),
  UNIQUE KEY `uq_adm_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela dbouvidoria.tbmanifest
CREATE TABLE IF NOT EXISTS `tbmanifest` (
  `IDmanifest` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `protocolo` varchar(20) NOT NULL COMMENT 'Número de protocolo público para rastreamento',
  `IDusu` int(11) unsigned DEFAULT NULL COMMENT 'NULL = manifestação anônima',
  `IDadm` int(11) unsigned DEFAULT NULL COMMENT 'NULL = sem atendente atribuído ainda',
  `IDtipo` int(11) unsigned NOT NULL,
  `IDsetor` int(11) unsigned DEFAULT NULL COMMENT 'Órgão da escola ao qual a manifestação se refere',
  `anonimo` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = anônima (IDusu gravado como NULL)',
  `manifest` text NOT NULL,
  `STATUS` enum('Aberta','Em análise','Respondida','Encerrada') NOT NULL DEFAULT 'Aberta' COMMENT 'Status atual da manifestação',
  `feedback` mediumtext DEFAULT NULL,
  `contato` varchar(150) DEFAULT NULL COMMENT 'Contato opcional (só para identificadas)',
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`IDmanifest`),
  UNIQUE KEY `uq_protocolo` (`protocolo`),
  KEY `idusu` (`IDusu`),
  KEY `idadm` (`IDadm`),
  KEY `idtipo` (`IDtipo`),
  KEY `idx_status` (`STATUS`),
  KEY `idx_setor` (`IDsetor`),
  KEY `idx_criado` (`criado_em`),
  CONSTRAINT `fk_manifest_adm` FOREIGN KEY (`IDadm`) REFERENCES `tbadm` (`IDadm`) ON DELETE SET NULL,
  CONSTRAINT `fk_manifest_setor` FOREIGN KEY (`IDsetor`) REFERENCES `tbsetores` (`IDsetor`) ON DELETE SET NULL,
  CONSTRAINT `fk_manifest_tipo` FOREIGN KEY (`IDtipo`) REFERENCES `tipos` (`IDtipo`),
  CONSTRAINT `fk_manifest_usu` FOREIGN KEY (`IDusu`) REFERENCES `tbusuarios` (`IDusu`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela dbouvidoria.tbmanifest_arquivos
CREATE TABLE IF NOT EXISTS `tbmanifest_arquivos` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `IDmanifest` int(11) unsigned NOT NULL COMMENT 'Manifestação à qual pertence',
  `nome_original` varchar(255) NOT NULL COMMENT 'Nome original do arquivo (exibição)',
  `nome_salvo` varchar(100) NOT NULL COMMENT 'Nome gerado aleatoriamente no servidor',
  `mime_type` varchar(100) NOT NULL COMMENT 'Tipo real do arquivo (verificado por finfo)',
  `tamanho` int(11) unsigned NOT NULL COMMENT 'Tamanho em bytes',
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_manifest` (`IDmanifest`),
  CONSTRAINT `fk_arquivo_manifest` FOREIGN KEY (`IDmanifest`) REFERENCES `tbmanifest` (`IDmanifest`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Arquivos anexados às manifestações';

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela dbouvidoria.tbsetores
CREATE TABLE IF NOT EXISTS `tbsetores` (
  `IDsetor` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(80) NOT NULL COMMENT 'Nome do setor/órgão',
  `descricao` varchar(200) DEFAULT NULL COMMENT 'Descrição opcional',
  `ativo` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=ativo, 0=desativado',
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`IDsetor`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Setores/órgãos da escola que recebem manifestações';

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela dbouvidoria.tbusuarios
CREATE TABLE IF NOT EXISTS `tbusuarios` (
  `IDusu` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(80) NOT NULL,
  `serie` int(1) DEFAULT NULL COMMENT 'Série/ano escolar',
  `curso` varchar(60) DEFAULT NULL,
  `matricula` varchar(40) DEFAULT NULL,
  `email` varchar(200) NOT NULL,
  `senha` varchar(255) NOT NULL COMMENT 'bcrypt hash — NUNCA armazenar senha em texto puro',
  `ativo` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=ativo, 0=bloqueado',
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`IDusu`),
  UNIQUE KEY `uq_email` (`email`),
  UNIQUE KEY `uq_matricula` (`matricula`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela dbouvidoria.tipos
CREATE TABLE IF NOT EXISTS `tipos` (
  `IDtipo` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `descricao` varchar(60) NOT NULL,
  PRIMARY KEY (`IDtipo`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para view dbouvidoria.v_manifestacoes
-- Criando tabela temporária para evitar erros de dependência de VIEW
CREATE TABLE `v_manifestacoes` (
	`IDmanifest` INT(11) UNSIGNED NOT NULL,
	`protocolo` VARCHAR(1) NOT NULL COMMENT 'Número de protocolo público para rastreamento' COLLATE 'utf8mb4_unicode_ci',
	`anonimo` TINYINT(1) NOT NULL COMMENT '1 = anônima (IDusu gravado como NULL)',
	`autor_nome` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`autor_email` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`autor_serie` INT(11) NULL,
	`autor_curso` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`tipo` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`setor` VARCHAR(1) NULL COMMENT 'Nome do setor/órgão' COLLATE 'utf8mb4_unicode_ci',
	`manifest` TEXT NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`STATUS` ENUM('Aberta','Em análise','Respondida','Encerrada') NOT NULL COMMENT 'Status atual da manifestação' COLLATE 'utf8mb4_unicode_ci',
	`feedback` MEDIUMTEXT NULL COLLATE 'utf8mb4_unicode_ci',
	`contato` VARCHAR(1) NULL COMMENT 'Contato opcional (só para identificadas)' COLLATE 'utf8mb4_unicode_ci',
	`atendente` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`criado_em` DATETIME NOT NULL,
	`atualizado_em` DATETIME NOT NULL
);

-- Removendo tabela temporária e criando a estrutura VIEW final
DROP TABLE IF EXISTS `v_manifestacoes`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_manifestacoes` AS SELECT
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
LEFT JOIN `tbsetores`   s ON m.IDsetor = s.IDsetor 
;

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
