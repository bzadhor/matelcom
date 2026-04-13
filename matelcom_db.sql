-- ============================================================
-- MATELCOM — Base de données MySQL
-- Site web de vente de matériel informatique
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- --------------------------------------------------------
-- Table `admins`
-- --------------------------------------------------------
CREATE TABLE `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(150) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `role_id` int(11) DEFAULT NULL,
  `is_super_admin` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `admins` (`id`, `username`, `password`, `email`, `is_super_admin`, `is_active`) VALUES
(1, 'admin', '$2y$10$77npToWMpgiVYXOxWDodV.Ivg/70XZ100yYKytzYNmOGuMLC3NvYW', 'admin@matelcom.ma', 1, 1);

-- --------------------------------------------------------
-- Table `admin_roles`
-- --------------------------------------------------------
CREATE TABLE `admin_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `description` varchar(255) DEFAULT NULL,
  `is_system` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `admin_roles` (`id`, `name`, `is_active`, `description`, `is_system`) VALUES
(1, 'super_admin', 1, 'Administrateur principal', 1),
(2, 'admin', 1, 'Administrateur', 0),
(3, 'editeur', 1, 'Éditeur de contenu', 0);

-- --------------------------------------------------------
-- Table `admin_role_permissions`
-- --------------------------------------------------------
CREATE TABLE `admin_role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `page` varchar(100) NOT NULL DEFAULT '',
  `can_read` tinyint(1) DEFAULT 0,
  `can_write` tinyint(1) DEFAULT 0,
  `page_key` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `admin_role_permissions` (`role_id`, `page`, `can_read`, `can_write`, `page_key`) VALUES
(1, 'all', 1, 1, '');

-- --------------------------------------------------------
-- Table `categories`
-- --------------------------------------------------------
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `icone` varchar(50) DEFAULT 'fas fa-folder',
  `ordre` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` (`nom`, `slug`, `icone`, `ordre`) VALUES
('Tous', 'all', 'fas fa-th-large', 0),
('RAM', 'ram', 'fas fa-memory', 1),
('SSD', 'ssd', 'fas fa-hdd', 2),
('HDD', 'hdd', 'fas fa-database', 3),
('Antivirus', 'antivirus', 'fas fa-shield-alt', 4),
('Logiciels', 'logiciels', 'fas fa-laptop-code', 5);

-- --------------------------------------------------------
-- Table `produits`
-- --------------------------------------------------------
CREATE TABLE `produits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(200) NOT NULL,
  `modele` varchar(100) DEFAULT NULL,
  `marque` varchar(100) DEFAULT NULL,
  `description_courte` varchar(300) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `specifications` text DEFAULT NULL COMMENT 'JSON key:value',
  `tags` varchar(500) DEFAULT NULL COMMENT 'JSON array',
  `categories` varchar(200) DEFAULT NULL COMMENT 'slugs séparés par espace',
  `badge_texte` varchar(60) DEFAULT NULL,
  `badge_couleur` varchar(30) DEFAULT '#D32F2F',
  `image` varchar(255) DEFAULT NULL,
  `brochure` varchar(255) DEFAULT NULL,
  `images_galerie` text DEFAULT NULL COMMENT 'JSON array de chemins',
  `disponibilite` enum('en_stock','sur_commande','rupture') DEFAULT 'en_stock',
  `statut` tinyint(1) DEFAULT 1,
  `ordre` int(11) DEFAULT 0,
  `en_vedette` tinyint(1) DEFAULT 0,
  `slug` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `produits` (`nom`, `modele`, `marque`, `description_courte`, `description`, `specifications`, `tags`, `categories`, `badge_texte`, `badge_couleur`, `disponibilite`, `statut`, `ordre`, `en_vedette`, `slug`) VALUES
('Mémoire RAM DDR4 8Go', 'DDR4-3200 8GB', 'Kingston', 'Barrette mémoire DDR4 8Go 3200MHz haute performance pour PC de bureau.', 'La Kingston DDR4-3200 est une barrette mémoire fiable et performante, idéale pour les configurations de bureau et les stations de travail. Elle offre des vitesses de transfert élevées et une faible latence pour un fonctionnement fluide du système.\r\n\r\nCertifiée et testée pour garantir une compatibilité maximale avec les cartes mères les plus populaires.', '[{"label":"Type","value":"DDR4"},{"label":"Capacité","value":"8 Go"},{"label":"Fréquence","value":"3200 MHz"},{"label":"Latence","value":"CL22"},{"label":"Tension","value":"1.2V"},{"label":"Format","value":"DIMM"}]', '["DDR4","8Go","3200MHz","Desktop","Kingston"]', 'ram', 'Populaire', '#D32F2F', 'en_stock', 1, 1, 1, 'kingston-ddr4-3200-8gb'),
('Mémoire RAM DDR4 16Go', 'DDR4-3200 16GB', 'Kingston', 'Barrette mémoire DDR4 16Go 3200MHz pour performances optimales.', 'Doublez les performances de votre système avec cette barrette Kingston DDR4 16Go. Idéale pour le multitâche, les logiciels professionnels et le gaming.', '[{"label":"Type","value":"DDR4"},{"label":"Capacité","value":"16 Go"},{"label":"Fréquence","value":"3200 MHz"},{"label":"Latence","value":"CL22"},{"label":"Tension","value":"1.2V"},{"label":"Format","value":"DIMM"}]', '["DDR4","16Go","3200MHz","Desktop"]', 'ram', '', '#D32F2F', 'en_stock', 1, 2, 1, 'kingston-ddr4-3200-16gb'),
('Mémoire RAM DDR5 16Go', 'DDR5-5600 16GB', 'Kingston', 'Barrette mémoire DDR5 16Go 5600MHz dernière génération.', 'Passez à la nouvelle génération avec la DDR5. Des performances inégalées pour les plateformes modernes.', '[{"label":"Type","value":"DDR5"},{"label":"Capacité","value":"16 Go"},{"label":"Fréquence","value":"5600 MHz"},{"label":"Latence","value":"CL40"},{"label":"Tension","value":"1.1V"},{"label":"Format","value":"DIMM"}]', '["DDR5","16Go","5600MHz","Nouvelle génération"]', 'ram', 'Nouveau', '#E65100', 'en_stock', 1, 3, 1, 'kingston-ddr5-5600-16gb'),
('SSD NVMe 500Go', 'A2000 500GB NVMe', 'Kingston', 'SSD NVMe M.2 500Go ultra-rapide pour démarrage et chargement instantanés.', 'Le Kingston A2000 offre des vitesses de lecture/écriture séquentielles allant jusqu''à 2200/2000 Mo/s. Format M.2 2280 compact.', '[{"label":"Interface","value":"NVMe PCIe Gen 3x4"},{"label":"Capacité","value":"500 Go"},{"label":"Lecture séq.","value":"2200 Mo/s"},{"label":"Écriture séq.","value":"2000 Mo/s"},{"label":"Format","value":"M.2 2280"},{"label":"Endurance","value":"150 TBW"}]', '["NVMe","500Go","M.2","PCIe Gen3","Rapide"]', 'ssd', 'Bestseller', '#2E7D32', 'en_stock', 1, 4, 1, 'kingston-a2000-500gb'),
('SSD SATA 1To', 'A400 1TB SATA', 'Kingston', 'SSD SATA III 1To pour upgrade de stockage rapide et fiable.', 'Remplacez votre disque dur par un SSD Kingston A400 pour des temps de démarrage et de chargement bien plus rapides. Format 2.5 pouces compatible avec tous les PC.', '[{"label":"Interface","value":"SATA III 6Gb/s"},{"label":"Capacité","value":"1 To"},{"label":"Lecture séq.","value":"500 Mo/s"},{"label":"Écriture séq.","value":"450 Mo/s"},{"label":"Format","value":"2.5 pouces"},{"label":"Endurance","value":"300 TBW"}]', '["SATA","1To","2.5 pouces","Upgrade","Fiable"]', 'ssd', '', '#D32F2F', 'en_stock', 1, 5, 0, 'kingston-a400-1tb'),
('Disque dur HDD 1To', 'Barracuda 1TB', 'Seagate', 'Disque dur interne 1To 7200rpm pour stockage de données volumineux.', 'Le Seagate Barracuda offre un stockage fiable de grande capacité pour vos données, documents, photos et vidéos.', '[{"label":"Interface","value":"SATA III 6Gb/s"},{"label":"Capacité","value":"1 To"},{"label":"Vitesse","value":"7200 rpm"},{"label":"Cache","value":"64 Mo"},{"label":"Format","value":"3.5 pouces"}]', '["HDD","1To","7200rpm","Stockage","Seagate"]', 'hdd', '', '#D32F2F', 'en_stock', 1, 6, 0, 'seagate-barracuda-1tb'),
('Disque dur HDD 4To', 'Barracuda 4TB', 'Seagate', 'Disque dur interne 4To pour stockage massif de données.', 'Capacité massive de 4To pour archivage, serveurs et stations de travail nécessitant un stockage volumineux.', '[{"label":"Interface","value":"SATA III 6Gb/s"},{"label":"Capacité","value":"4 To"},{"label":"Vitesse","value":"5400 rpm"},{"label":"Cache","value":"256 Mo"},{"label":"Format","value":"3.5 pouces"}]', '["HDD","4To","Stockage massif","Seagate"]', 'hdd', '', '#D32F2F', 'en_stock', 1, 7, 0, 'seagate-barracuda-4tb'),
('Kaspersky Internet Security 1 Poste', 'KIS 1PC 1An', 'Kaspersky', 'Protection complète contre les virus, malwares et menaces en ligne pour 1 PC.', 'Kaspersky Internet Security offre une protection premium contre les cybermenaces. Inclut antivirus, pare-feu, protection bancaire et VPN.', '[{"label":"Licence","value":"1 poste / 1 an"},{"label":"Protection","value":"Antivirus + Firewall"},{"label":"Fonctions","value":"Safe Banking, VPN, Anti-phishing"},{"label":"Compatibilité","value":"Windows, Mac, Android"}]', '["Kaspersky","Antivirus","1 poste","1 an","Sécurité"]', 'antivirus', 'Partenaire officiel', '#D32F2F', 'en_stock', 1, 8, 1, 'kaspersky-kis-1pc'),
('Kaspersky Total Security 3 Postes', 'KTS 3PC 1An', 'Kaspersky', 'Suite de sécurité complète pour 3 appareils avec contrôle parental et gestionnaire de mots de passe.', 'Kaspersky Total Security protège jusqu''à 3 appareils avec la suite complète incluant antivirus, VPN, contrôle parental, gestionnaire de mots de passe et sauvegarde.', '[{"label":"Licence","value":"3 postes / 1 an"},{"label":"Protection","value":"Suite complète"},{"label":"Fonctions","value":"Antivirus, VPN, Parental Control, Password Manager, Backup"},{"label":"Compatibilité","value":"Windows, Mac, Android, iOS"}]', '["Kaspersky","Total Security","3 postes","1 an","Premium"]', 'antivirus', 'Best Value', '#2E7D32', 'en_stock', 1, 9, 1, 'kaspersky-kts-3pc'),
('Microsoft Office 2021 Pro Plus', 'Office 2021 Pro', 'Microsoft', 'Suite bureautique complète avec Word, Excel, PowerPoint, Outlook et plus.', 'Microsoft Office 2021 Professionnel Plus inclut toutes les applications essentielles pour la productivité : Word, Excel, PowerPoint, Outlook, Access, Publisher.', '[{"label":"Licence","value":"1 PC / Perpétuelle"},{"label":"Applications","value":"Word, Excel, PowerPoint, Outlook, Access, Publisher"},{"label":"Compatibilité","value":"Windows 10/11"},{"label":"Type","value":"Licence perpétuelle"}]', '["Microsoft","Office 2021","Pro Plus","Licence","Perpétuelle"]', 'logiciels', 'Partenaire officiel', '#1565C0', 'en_stock', 1, 10, 1, 'microsoft-office-2021-pro'),
('Windows 11 Pro', 'Win11 Pro', 'Microsoft', 'Système d''exploitation Windows 11 Professionnel - licence officielle.', 'Windows 11 Pro offre une expérience moderne, sécurisée et productive. Idéal pour les entreprises et les professionnels.', '[{"label":"Licence","value":"1 PC / Perpétuelle"},{"label":"Version","value":"Professionnel"},{"label":"Architecture","value":"64 bits"},{"label":"Type","value":"Licence OEM/Retail"}]', '["Windows 11","Pro","Licence","Microsoft","OS"]', 'logiciels', '', '#1565C0', 'en_stock', 1, 11, 0, 'windows-11-pro'),
('Microsoft 365 Business', 'M365 Business', 'Microsoft', 'Abonnement Microsoft 365 Business avec applications Office + cloud.', 'Microsoft 365 Business inclut les applications Office classiques avec OneDrive 1To, Teams, Exchange et la mise à jour automatique.', '[{"label":"Licence","value":"1 utilisateur / 1 an"},{"label":"Applications","value":"Word, Excel, PowerPoint, Outlook, Teams"},{"label":"Stockage cloud","value":"1 To OneDrive"},{"label":"Type","value":"Abonnement annuel"}]', '["Microsoft 365","Business","Cloud","Teams","Abonnement"]', 'logiciels', 'Nouveau', '#E65100', 'en_stock', 1, 12, 0, 'microsoft-365-business');

-- --------------------------------------------------------
-- Table `produit_images`
-- --------------------------------------------------------
CREATE TABLE `produit_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `produit_id` int(11) NOT NULL,
  `image` varchar(255) NOT NULL,
  `ordre` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `produit_id` (`produit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table `devis`
-- --------------------------------------------------------
CREATE TABLE `devis` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom_complet` varchar(150) NOT NULL,
  `societe` varchar(200) DEFAULT NULL,
  `telephone` varchar(50) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `produits_demandes` text DEFAULT NULL,
  `quantite` varchar(100) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `fichier` varchar(255) DEFAULT NULL,
  `statut` enum('nouveau','en_cours','traite','archive') DEFAULT 'nouveau',
  `note_admin` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table `temoignages`
-- --------------------------------------------------------
CREATE TABLE `temoignages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `role` varchar(100) DEFAULT NULL,
  `societe` varchar(100) DEFAULT NULL,
  `texte` text NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `note` tinyint DEFAULT 5,
  `statut` tinyint(1) DEFAULT 1,
  `ordre` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `temoignages` (`nom`, `role`, `societe`, `texte`, `note`, `statut`, `ordre`) VALUES
('Ahmed Bennani', 'Directeur IT', 'TechSolutions Maroc', 'Matelcom est notre fournisseur de confiance depuis plus de 10 ans. Produits authentiques, prix compétitifs et un service client irréprochable.', 5, 1, 1),
('Sara El Fassi', 'Responsable Achats', 'MediaGroup', 'La réactivité de l''équipe Matelcom est remarquable. Nos commandes sont toujours livrées dans les délais avec un support technique de qualité.', 5, 1, 2),
('Karim Tazi', 'Gérant', 'CyberNet Rabat', 'Un partenaire fiable pour tout ce qui est matériel informatique. Les licences Microsoft et Kaspersky sont toujours au meilleur prix.', 4, 1, 3);

-- --------------------------------------------------------
-- Table `menu_items`
-- --------------------------------------------------------
CREATE TABLE `menu_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `label` varchar(100) NOT NULL,
  `url` varchar(255) NOT NULL,
  `target` varchar(10) DEFAULT '_self',
  `ordre` int(11) DEFAULT 0,
  `actif` tinyint(1) DEFAULT 1,
  `parent_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `menu_items` (`label`, `url`, `target`, `ordre`, `actif`) VALUES
('Accueil', 'index.php', '_self', 1, 1),
('Produits', '#produits', '_self', 2, 1),
('À propos', '#apropos', '_self', 3, 1),
('Contact', '#contact', '_self', 4, 1);

-- --------------------------------------------------------
-- Table `parametres`
-- --------------------------------------------------------
CREATE TABLE `parametres` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cle` varchar(100) NOT NULL,
  `valeur` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cle` (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `parametres` (`cle`, `valeur`) VALUES
('site_nom', 'MATELCOM'),
('site_slogan', 'Premium Solutions'),
('couleur_principale', '#D32F2F'),
('hero_titre', 'Votre partenaire <span>informatique</span> de confiance'),
('hero_sous_titre', 'Depuis plus de 24 ans, MATELCOM fournit les meilleures solutions informatiques aux entreprises et particuliers au Maroc. RAM, stockage, logiciels — tout ce dont vous avez besoin.'),
('hero_badge', 'Partenaire officiel Microsoft & Kaspersky'),
('stat_1_valeur', '24+'),
('stat_1_label', 'Années d''expérience'),
('stat_2_valeur', '1000+'),
('stat_2_label', 'Clients satisfaits'),
('stat_3_valeur', '50+'),
('stat_3_label', 'Produits disponibles'),
('about_titre', 'Plus de 24 ans d''expertise <span>informatique</span>'),
('about_texte', 'MATELCOM est un acteur majeur de la distribution de matériel informatique au Maroc. Partenaire officiel de Microsoft et Kaspersky, nous proposons des produits authentiques avec garantie constructeur à des prix compétitifs.'),
('about_annees', '24+'),
('contact_telephone', '+212 5XX-XXXXXX'),
('contact_whatsapp', '+212 6XX-XXXXXX'),
('contact_email', 'contact@matelcom.ma'),
('contact_adresse', 'Rabat, Maroc'),
('whatsapp_numero', '212600000000'),
('whatsapp_message', 'Bonjour, je souhaite un devis pour'),
('footer_texte', 'Votre partenaire informatique de confiance depuis plus de 24 ans. Produits authentiques, prix compétitifs.'),
('footer_facebook', '#'),
('footer_instagram', '#'),
('footer_linkedin', '#'),
('footer_whatsapp', '#'),
('logo', 'logo/matelcom_logo.png'),
('recaptcha_site_key', ''),
('recaptcha_secret_key', '');

-- --------------------------------------------------------
-- Table `visites`
-- --------------------------------------------------------
CREATE TABLE `visites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page` varchar(255) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `pays` varchar(50) DEFAULT NULL,
  `ville` varchar(100) DEFAULT NULL,
  `appareil` varchar(20) DEFAULT NULL,
  `navigateur` varchar(100) DEFAULT NULL,
  `referer` varchar(500) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table `partenaires`
-- --------------------------------------------------------
CREATE TABLE `partenaires` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `url` varchar(255) DEFAULT '#',
  `ordre` int(11) DEFAULT 0,
  `actif` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `partenaires` (`nom`, `url`, `ordre`, `actif`) VALUES
('Microsoft', '#', 1, 1),
('Kaspersky', '#', 2, 1),
('Kingston', '#', 3, 1),
('Seagate', '#', 4, 1);

COMMIT;
