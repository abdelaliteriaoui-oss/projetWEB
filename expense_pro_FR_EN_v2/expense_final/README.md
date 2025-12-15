# ExpensePro - Système de Gestion des Frais de Déplacement

![ExpensePro](https://img.shields.io/badge/Version-2.0-blue) ![PHP](https://img.shields.io/badge/PHP-8.0+-purple) ![Bootstrap](https://img.shields.io/badge/Bootstrap-5-blueviolet)

## 🎯 Présentation

**ExpensePro** est une application web moderne de gestion des frais de déplacement professionnel, inspirée des meilleures solutions du marché comme **Expensify**, **SAP Concur** et **Zoho Expense**.

### ✨ Fonctionnalités Principales

- 📱 **Interface moderne et responsive** - Design épuré inspiré des meilleures applications SaaS
- 🔐 **Authentification sécurisée** - Gestion des sessions et protection des données
- 📝 **Création de demandes intuitive** - Formulaire multi-lignes avec upload de justificatifs
- 🔄 **Workflow de validation** - Circuit Employé → Manager → Admin
- 📊 **Dashboard personnalisé** - Statistiques et graphiques par rôle
- 🔔 **Notifications temps réel** - Alertes sur les actions importantes
- 🌙 **Mode sombre** - Thème clair/sombre personnalisable
- 📄 **Export PDF** - Impression des demandes

---

## 🚀 Installation

### Prérequis

- PHP 7.4 ou supérieur
- MySQL 5.7 ou MariaDB 10.3+
- Serveur web (Apache/Nginx)
- Extension PHP : PDO, PDO_MySQL

### Étapes d'installation

1. **Cloner/Copier le projet** dans votre dossier web (ex: `htdocs` ou `www`)

```bash
cp -r expense_pro /var/www/html/
```

2. **Importer la base de données**

```bash
mysql -u root -p < gestion_frais__1_.sql
```

3. **Configurer la connexion** dans `includes/config.php`

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'gestion_frais');
define('DB_USER', 'root');
define('DB_PASS', '');
```

4. **Créer le dossier d'uploads** avec les bonnes permissions

```bash
mkdir -p uploads/justificatifs uploads/profiles
chmod 755 uploads -R
```

5. **Accéder à l'application** via `http://localhost/expense_pro`

---

## 👥 Comptes de Démonstration

| Rôle | Email | Mot de passe |
|------|-------|--------------|
| **Admin** | admin@societe.com | admin123 |
| **Manager** | youssef.benali@societe.com | manager123 |
| **Employé** | fatima.idrissi@societe.com | employe123 |

---

## 📂 Structure du Projet

```
expense_pro/
├── assets/
│   ├── css/
│   │   └── style.css          # Styles CSS modernes
│   ├── js/
│   │   └── app.js             # JavaScript interactif
│   └── img/                   # Images et icônes
├── includes/
│   ├── config.php             # Configuration & fonctions
│   ├── header.php             # En-tête commun
│   └── footer.php             # Pied de page commun
├── uploads/
│   ├── justificatifs/         # Fichiers justificatifs
│   └── profiles/              # Photos de profil
├── index.php                  # Point d'entrée
├── login.php                  # Page de connexion
├── logout.php                 # Déconnexion
├── dashboard.php              # Tableau de bord
├── nouvelle_demande.php       # Créer une demande
├── mes_demandes.php           # Liste des demandes
├── voir_demande.php           # Détail d'une demande
├── traiter_demande.php        # Validation/Rejet
├── gestion_utilisateurs.php   # Admin: Gestion users
├── profil.php                 # Profil utilisateur
└── README.md                  # Documentation
```

---

## 🎨 Design & UX

### Inspirations

Le design s'inspire des meilleures pratiques des applications leaders:

- **Expensify** → Simplicité du formulaire de dépenses
- **SAP Concur** → Dashboard professionnel avec KPIs
- **Zoho Expense** → Timeline et workflow visuel

### Palette de Couleurs

| Couleur | Hex | Usage |
|---------|-----|-------|
| Primary | `#0066FF` | Actions principales |
| Secondary | `#00D4AA` | Accents |
| Success | `#10B981` | Validations |
| Warning | `#F59E0B` | Attente |
| Danger | `#EF4444` | Rejets |

### Composants UI

- ✅ Cards avec ombres et animations
- ✅ Badges de statut colorés
- ✅ Timeline pour l'historique
- ✅ Modals pour les confirmations
- ✅ Toast notifications
- ✅ Formulaires avec validation
- ✅ Tables responsive
- ✅ Graphiques Chart.js

---

## 🔄 Workflow de Validation

```
┌─────────────┐    ┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│  Brouillon  │───▶│   Soumise   │───▶│  Validée    │───▶│  Approuvée  │
│  (Employé)  │    │  (Employé)  │    │  (Manager)  │    │   (Admin)   │
└─────────────┘    └─────────────┘    └─────────────┘    └─────────────┘
                          │                  │
                          ▼                  ▼
                   ┌─────────────┐    ┌─────────────┐
                   │  Rejetée    │    │  Rejetée    │
                   │  (Manager)  │    │   (Admin)   │
                   └─────────────┘    └─────────────┘
```

---

## 📊 Fonctionnalités par Rôle

### 👤 Employé
- Créer des demandes de frais
- Joindre des justificatifs
- Suivre le statut des demandes
- Consulter l'historique
- Gérer son profil

### 👔 Manager
- Valider/Rejeter les demandes de son équipe
- Voir les statistiques de l'équipe
- Filtrer par employé, date, statut
- Commenter les décisions

### 🔧 Administrateur
- Approuver les demandes validées
- Gérer tous les utilisateurs
- Configurer les catégories de frais
- Accéder aux rapports globaux
- Exporter les données

---

## 🛡️ Sécurité

- ✅ Mots de passe hashés (bcrypt)
- ✅ Protection CSRF
- ✅ Validation des entrées
- ✅ Requêtes préparées (PDO)
- ✅ Sessions sécurisées
- ✅ Contrôle d'accès par rôle

---

## 📈 Améliorations Futures

- [ ] Scan OCR des reçus
- [ ] Calcul automatique des indemnités kilométriques
- [ ] Intégration avec les systèmes comptables
- [ ] Application mobile (PWA)
- [ ] Export Excel/CSV
- [ ] Multi-devises
- [ ] Règles de validation automatiques

---

## 🤝 Support

Pour toute question ou suggestion, veuillez créer une issue dans le repository.

---

## 📜 Licence

Ce projet est distribué sous licence MIT.

---

**Développé avec ❤️ pour une gestion des frais simplifiée**
