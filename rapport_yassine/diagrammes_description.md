# Descriptions des Diagrammes UML - Projet MediTN

---

## Diagramme de Classes - Sprint 1 (fig:sprint1_class)

### Description de création
- Utiliser un outil UML (comme StarUML, PlantUML ou Lucidchart)
- Créer 4 formes rectangulaires pour les entités principales : Utilisateur, Établissement, StaffProfile, Candidature
- Ajouter les attributs dans chaque entité :
  - **Utilisateur** : email, password, nom, prénom, telephone, actif
  - **Établissement** : tenantId, nom, code, type, ville, actif
  - **StaffProfile** : staffType (DOCTOR, NURSE, SECRETARY, MANAGER)
  - **Candidature** : status (PENDING, APPROVED, REJECTED)
- Dessiner des flèches entre les entités avec les libellés de relations :
  - "travaille dans" entre Utilisateur et Établissement
  - "a un profil" entre Utilisateur et StaffProfile
  - "rattaché à" entre StaffProfile et Établissement
  - "concerne" entre Candidature et Établissement

### Sprint
Sprint 1 : Authentification et Gestion des Établissements

### Objectif
Illustrer la structure des données de base et les relations entre utilisateurs, établissements et le processus de candidature.

### Description
Ce diagramme modélise le cœur de l'authentification et de la gestion multi-tenant. Il montre comment les utilisateurs sont associés aux établissements via les profils staff, et comment une candidature peut mener à la création d'un établissement. Ce diagramme est fondamental car il établit la base de l'architecture multi-tenant par tenantId, essentielle pour l'isolation des données entre établissements médicaux différents dans la plateforme SaaS.

---

## Diagramme de Cas d'Utilisation - Sprint 1 (tab:sprint1_usecase)

### Description de création
- Créer un tableau avec deux colonnes : "Acteur" et "Cas d'utilisation"
- Lister les acteurs principaux : Patient, Manager, Admin Plateforme, Système
- Pour chaque acteur, énumérer les actions possibles :
  - Patient : "Créer un compte", "Se connecter", "Consulter son profil"
  - Manager : "Soumettre une candidature établissement", "Gérer le personnel"
  - Admin Plateforme : "Gérer les établissements", "Approuver/Rejeter les candidatures"
  - Système : "Envoyer email de confirmation", "Générer token JWT", "Filtrer par tenant"

### Sprint
Sprint 1 : Authentification et Gestion des Établissements

### Objectif
Définir la portée fonctionnelle initiale du système et les interactions possibles pour chaque type d'utilisateur.

### Description
Ce diagramme tabulaire spécifie les fonctionnalités de base accessibles par chaque acteur. Il sert de base pour le développement des interfaces utilisateur et la définition des endpoints API. La présence d'un acteur "Système" indique les processus automatisés comme l'envoi d'emails et le filtrage tenant, ce qui est crucial pour la conformité RGPD dans le contexte tunisien (loi n°2004-63).

---

## Diagramme de Classes - Sprint 2 (fig:sprint2_class)

### Description de création
- Utiliser la même méthode que le diagramme Sprint 1
- Créer 4 formes : DossierMedical, RendezVous, PatientProfile, Utilisateur
- Ajouter les attributs :
  - **DossierMedical** : diagnostic, notes, recordType, title, dateCreation
  - **RendezVous** : dateHeure, statut, motif, type (CONSULTATION, SUIVI, URGENCE)
  - **PatientProfile** : dateNaissance, genre, groupeSanguin, allergies
  - **Utilisateur** : indication que c'est patient ou médecin
- Dessiner les relations :
  - "associé à" entre DossierMedical et RendezVous
  - "profil de" entre PatientProfile et Utilisateur
  - "appartient à" entre DossierMedical et PatientProfile
  - "participants" entre RendezVous et Utilisateur

### Sprint
Sprint 2 : Dossier Médical Électronique et Rendez-vous

### Objectif
Représenter la structure des données liées aux dossiers médicaux et aux rendez-vous, ainsi que leurs interdépendances.

### Description
Ce diagramme est essentiel pour comprendre comment les rendez-vous sont liés aux dossiers médicaux. Il montre la séparation entre le créneau horaire (RendezVous) et le dossier patient (DossierMedical), ce qui est crucial pour la gestion des flux de travail cliniques. La relation avec PatientProfile permet de distinguer les données spécifiques aux patients (groupe sanguin, allergies) des données d'authentification communes.

---

## Diagramme de Cas d'Utilisation - Sprint 2 (tab:sprint2_usecase)

### Description de création
- Tableau à deux colonnes comme pour le Sprint 1
- Acteurs : Secrétaire, Médecin, Patient, Système
- Cas d'utilisation :
  - Secrétaire : "Créer dossier patient", "Rechercher un patient", "Planifier un rendez-vous", "Consulter le calendrier"
  - Médecin : "Consulter le DME", "Mettre à jour le diagnostic", "Prescrire des examens", "Visualiser l'agenda"
  - Patient : "Créer son profil", "Consulter son dossier", "Voir ses rendez-vous"
  - Système : "Filtrer les dossiers par établissement (tenant)", "Gérer les conflits de planning"

### Sprint
Sprint 2 : Dossier Médical Électronique et Rendez-vous

### Objectif
Spécifier les interactions détaillées pour la gestion des dossiers médicaux et des rendez-vous.

### Description
Ce diagramme montre l'interaction entre les différents acteurs dans la gestion clinique. La distinction entre secrétaire et médecin dans les cas d'utilisation reflète la répartition des tâches dans les établissements médicaux tunisiens. Le cas "Gérer les conflits de planning" est particulièrement important pour éviter les doubles réservations, un problème fréquent dans les systèmes existants.

---

## Diagramme de Classes - Sprint 3 (fig:sprint3_class)

### Description de création
- 4 formes principales : Consultation, Prescription, ResultatLabo, Facture
- Attributs :
  - **Consultation** : statut, diagnosis, symptoms, clinicalNotes, vitalSigns (embarqué), followUp
  - **Prescription** : médicaments, posologie, durée, datePrescription
  - **ResultatLabo** : typeAnalyse, resultats, valeursCritiques
  - **Facture** : montant, statut, dateEmission, articles facturés
- Relations :
  - "génère" entre Consultation et Prescription
  - "demande" entre Consultation et ResultatLabo
  - "produit" entre Consultation et Facture

### Sprint
Sprint 3 : Consultations, Prescriptions, Laboratoire et Facturation

### Objectif
Modéliser les flux entre la consultation médicale, les prescriptions, les résultats labo et la facturation.

### Description
Ce diagramme lie les processus cliniques aux aspects administratifs et financiers. La relation "Embedded VitalSigns" montre que les constantes vitales sont stockées directement dans la consultation, ce qui optimise l'accès aux données. Le diagramme reflète la complexité du cycle de soins complet, de la consultation au paiement, en passant par les analyses biologiques.

---

## Diagramme de Cas d'Utilisation - Sprint 3 (tab:sprint3_usecase)

### Description de création
- Acteurs : Médecin, Secrétaire, Technicien Labo, Patient (mobile)
- Cas :
  - Médecin : "Consulter la liste des RDV du jour", "Démarrer une consultation", "Saisir les constantes vitales", "Poser un diagnostic", "Prescrire un traitement", "Demander des examens"
  - Secrétaire : "Générer une facture", "Enregistrer un paiement", "Consulter l'historique des factures"
  - Technicien Labo : "Saisir des résultats d'analyses", "Déclencher une alerte critique", "Associer au dossier patient"
  - Patient (mobile) : "Consulter les résultats d'analyses", "Voir les ordonnances", "Accéder au dossier médical"

### Sprint
Sprint 3 : Consultations, Prescriptions, Laboratoire et Facturation

### Objectif
Détailler les workflows du médecin, du secrétaire et du laboratoire, ainsi que les accès mobiles pour les patients.

### Description
Ce diagramme montre la spécialisation des rôles dans un établissement médical. Le suivi des "valeurs critiques" par le technicien de labo est crucial pour la sécurité des patients. L'accès mobile pour les patients représente l'innovation dans le contexte tunisien où les patients n'ont généralement pas accès numérique à leurs résultats.

---

## Diagramme de Classes - Sprint 4 (fig:sprint4_class)

### Description de création
- 6 formes : PatientStay, SurgeryCase, Room, Bed, Notification, Rapport
- Attributs :
  - **PatientStay** : stayType, status, admissionDateTime, dischargeDateTime
  - **SurgeryCase** : statut, procedureCode, urgencyLevel, scheduledStart/End
  - **Room** : code, name, roomType, floor, capacity
  - **Bed** : code, status
  - **Notification** : type, message, destinataire, lu
  - **Rapport** : type, periode, données, généréLe
- Relations :
  - "est dans" entre PatientStay et Room
  - "pré/post-op" entre PatientStay et SurgeryCase
  - "contient" entre Room et Bed
  - "assigné à" entre Bed et PatientStay

### Sprint
Sprint 4 : Gestion du Personnel, Hospitalisation et Finalisation

### Objectif
Modéliser la gestion des hospitalisations, des interventions chirurgicales, des chambres et des lits.

### Description
Ce diagramme finalise l'architecture en couvrant les processus hospitaliers. Il montre la hiérarchie logique : établissement contient des chambres, qui contiennent des lits, affectés aux séjours patients. La relation "pré/post-op" entre séjour et chirurgie est key pour la planification des soins. Les notifications permettent une communication interne essentielle dans les établissements médicaux.

---

## Diagramme de Cas d'Utilisation - Sprint 4 (tab:sprint4_usecase)

### Description de création
- Acteurs : Manager, Médecin, Chirurgien, Admin établissement, Système
- Cas :
  - Manager : "Ajouter du personnel avec type et mot de passe", "Visualiser la liste du personnel"
  - Médecin : "Admettre un patient", "Planifier une sortie", "Gérer les lits", "Visualiser les séjours en cours"
  - Chirurgien : "Planifier une intervention", "Assigner l'équipe", "Choisir la salle", "Suivre le statut"
  - Admin établissement : "Gérer les chambres (création, capacité, service)", "Gérer les lits (actif/inactif)", "Consulter les rapports d'activité"
  - Système : "Notifier les parties prenantes", "Générer des rapports périodiques"

### Sprint
Sprint 4 : Gestion du Personnel, Hospitalisation et Finalisation

### Objectif
Définir les dernières fonctionnalités pour finaliser la plateforme complète.

### Description
Ce diagramme complète le périmètre fonctionnel en couvrant la gestion hospitalière. La distinction chirurgien/médecin dans les cas d'utilisation est pertinente pour les établissements tunisiens où les chirurgiens ont des responsabilités spécifiques. Les rapports d'activité sont cruciaux pour la gouvernance hospitalière et la conformité administrative.