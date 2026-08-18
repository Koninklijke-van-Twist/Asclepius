id: 2026-08-18-ticket-sort-preferences
date: 2026-08-18
title: Définir son propre tri des tickets
author: Tim Falken

Sous **Préférences > Apparence**, vous pouvez maintenant composer l’ordre des tickets avec plusieurs règles de tri. Les règles peuvent être déplacées, ajoutées, supprimées avec confirmation et remises à l’ordre par défaut. L’aperçu à droite utilise maintenant plus de dix tickets, avec davantage d’exemples résolus, pour montrer immédiatement l’effet du tri choisi. L’ordre par défaut est maintenant **Ouverts d’abord**, **Priorité haute d’abord**, puis **Les moins récents d’abord** et **Numéro le plus bas d’abord**. Chaque option a aussi une courte explication.

---
id: 2026-08-13-open-trend-hover
date: 2026-08-13
title: Graphique tendance : valeur au survol
author: Tim Falken

Dans le graphique **Tickets ouverts dans le temps**, les points fixes sur les lignes ont disparu. Au survol (ou au toucher), seul le point le plus proche affiche une pastille avec le nombre actuel.

---
id: 2026-08-13-hourly-open-tickets-trend
date: 2026-08-13
title: Graphique tickets ouverts à l’heure
author: Tim Falken

Le graphique de tendance dans **Statistiques ICT** utilise désormais des instantanés **horaires** (`hourly.php`) au lieu des nocturnes. Les heures inchangées ne sont pas réenregistrées ; la ligne les remplit avec la dernière valeur connue. L’axe montre toujours les jours, avec le détail horaire dans la ligne. `nightly.php` ne gère plus que la question du thé.

---
id: 2026-08-05-date-format
date: 2026-08-05
title: Dates au format 15 juin 2026
author: Tim Falken

Les dates sur les pages de tickets (création/mise à jour, échéance, axes du graphique, détail congé) utilisent désormais le format lisible **15 juin 2026** (avec l’heure si besoin : **15 juin 2026 14:30**), dans la langue de l’interface.

---
id: 2026-08-05-open-tickets-trend
date: 2026-08-13
title: Tickets ouverts dans le temps par catégorie
author: Tim Falken

Dans **Statistiques ICT**, un sous-onglet **Tickets ouverts dans le temps** montre un graphique SVG net des tickets ouverts par catégorie. Par défaut : le mois dernier jusqu’à aujourd’hui ; les sélecteurs de date permettent d’ajuster la période en direct. Cliquez sur une catégorie dans la légende pour afficher ou masquer cette ligne. Les instantanés sont enregistrés **chaque heure** lorsque le nombre de tickets ouverts par catégorie change (`hourly.php`).

---
id: 2026-08-05-ticket-appearance-prefs
date: 2026-08-05
title: Préférences avec apparence
author: Tim Falken

L’onglet **Préférences e-mail** s’appelle désormais **Préférences**. Les notifications e-mail restent en haut ; en dessous, **Apparence** permet de choisir l’aspect des tickets : pastilles de priorité, temps ouvert, couleur du bord supérieur (statut/assigné/priorité/catégorie) et si les tickets clôturés sont plus discrets. À droite, des tickets d’exemple se mettent à jour en direct ; les choix s’appliquent partout dans le système.

---
id: 2026-08-04-ict-roles-afas
date: 2026-08-04
title: Rôles ICT et catégorie AFAS
author: Tim Falken

Il existe une nouvelle catégorie de tickets **AFAS**. Les administrateurs ICT complets peuvent aussi gérer des rôles limités sous **Rôles** : nom du rôle, catégories liées et membres (adresses e-mail).

Les membres d'un rôle ne voient que les tickets des catégories de leur rôle, avec une navigation du type `<rôle>-aperçu` et `<rôle>-statistiques`. Sous Paramètres, ils gèrent les congés et l'attribution automatique pour les catégories de leur rôle. Les choix d'assignation et les notifications suivent qui est éligible pour cette catégorie. La recherche par numéro de ticket et les liens de ticket respectent le même accès par catégorie. Un utilisateur ne peut appartenir qu'à **un seul rôle** ; à l'ajout, des suggestions provenant de la liste d'utilisateurs connue sont proposées. Les rafraîchissements en direct (stats/poll tickets) restent dans les mêmes restrictions de rôle. Changer vers une catégorie hors rôle affiche un avertissement et force la réassignation. Les utilisateurs ICT limités conservent aussi l'onglet **Tous les tickets** (mêmes droits qu'un utilisateur normal) ; les administrateurs ICT complets n'en ont pas besoin. Le filtre **Assigné à** mémorise aussi correctement les membres de rôle (pas seulement les admins complets).

---
id: 2026-08-04-priority-edge-markers
date: 2026-08-04
title: Pastilles de priorité sur les tickets ouverts
author: Tim Falken

Dans l’**aperçu ICT**, les tickets ouverts avec priorité 1 ou 2 affichent une pastille sur le bord gauche : orange avec `!` pour la priorité 1, et rouge clignotant avec `!!` pour la priorité 2.

---
id: 2026-08-04-ticket-sort-oldest-first
date: 2026-08-04
title: Anciens tickets ouverts en premier
author: Tim Falken

Les tickets ouverts restent en premier (non résolus avant résolus), puis par priorité (haute vers basse). À priorité égale, les **tickets plus anciens apparaissent désormais avant les plus récents**, pour que les tickets ouverts depuis plus longtemps soient plus visibles.

---
id: 2026-08-04-presence-sidebar
date: 2026-08-04
title: Présence via Janus
author: Tim Falken

Sur le côté, un **aperçu de présence** montre qui est au bureau ou à domicile aujourd’hui, absent, malade ou en vacances. Les données viennent de [Janus](../janus/) et uniquement des collègues qui y utilisent le **suivi d’heures complet**.

Vous n’y figurez pas encore (en tant qu’admin ICT) ? Cliquez sur l’aperçu pour une explication, ou ouvrez [Janus](../janus/), activez le suivi d’heures complet et continuez à l’utiliser — vous apparaîtrez ensuite automatiquement.

---
id: 2026-07-22-ghost-messages
date: 2026-07-22
title: Messages fantômes dans l’aperçu ICT
author: Tim Falken

Dans l’**aperçu ICT**, vous pouvez activer le mode fantôme en répondant (bouton fantôme à côté du clavier). Ces messages ne sont visibles que pour l’ICT dans cet aperçu, avec un style violet et un bord ondulé. Les changements de statut et autres notes système restent des messages normaux.

---
id: 2026-07-22-custom-ticket-statuses
date: 2026-07-22
title: Statuts de ticket personnalisés
author: Tim Falken

En tant qu’admin ICT, vous pouvez cliquer sur **Autre** lors du changement de statut d’un ticket et saisir un nom de statut personnalisé. Ce statut apparaît dans la liste de choix et dans les bulles de filtre tant que des tickets l’utilisent. Un filtre pour un statut que vous avez créé est activé par défaut. Au survol d’une bulle personnalisée, vous voyez qui l’a créée. Les variantes de majuscules sont fusionnées, et la couleur est dérivée du nom.

---
id: 2026-07-21-exact-ticket-number-search
date: 2026-07-21
title: Recherche par numéro de ticket ignore les filtres
author: Tim Falken

Lorsque vous saisissez un **numéro de ticket** dans la barre de recherche (par exemple `42` ou `#42`), ce ticket apparaît toujours dans les résultats — même s'il ne correspond pas aux filtres actifs de statut, catégorie ou intervenant.

---
id: 2026-07-17-api-user-names
date: 2026-07-17
title: Noms d'utilisateur dans les réponses API
author: Tim Falken

Les réponses API qui contiennent un e-mail utilisateur incluent désormais aussi le **nom d'affichage** correspondant (via l'annuaire Graph). Les listes de participants incluent un tableau `participants` avec e-mail et nom.

---
id: 2026-07-17-api-docs
date: 2026-07-17
title: Documentation API dans Paramètres
author: Tim Falken

En bas des **Paramètres**, un bouton **API** ouvre la documentation de l'API Asclepius (authentification, points d'accès et exemples) dans l'application.

---
id: 2026-07-17-prefs-led-ticket-filters
date: 2026-07-17
title: Les filtres restent enregistrés dans votre profil
author: Tim Falken

Les filtres de tickets (statut, catégorie, assigné, recherche) sont désormais stockés dans vos préférences utilisateur plutôt que dans l'URL. Après l'enregistrement d'un ticket, vos filtres restent. Les filtres n'apparaissent temporairement dans l'URL que lorsque vous les modifiez ; ensuite l'URL est à nouveau nettoyée.

---
id: 2026-07-17-attachment-open-new-tab
date: 2026-07-17
title: Pièces jointes ouvertes dans un nouvel onglet
author: Tim Falken

Un clic sur le nom d'une pièce jointe ouvre désormais le fichier dans un **nouvel onglet**. La page du ticket reste ouverte. Les aperçus dans la modal restent inchangés.

---
id: 2026-07-14-tickets-per-page-preference
date: 2026-07-14
title: Nombre de tickets par page configurable
author: Tim Falken

Vous pouvez désormais choisir combien de tickets s'affichent par page (5 à 100, 20 par défaut). Le réglage se trouve à droite de **Réinitialiser les filtres** dans le bloc de filtres, ou dans le même bloc sur **Mes tickets**. Votre choix est enregistré et s'applique partout : Mes tickets, Aperçu ICT et Tous les tickets.

---
id: 2026-07-10-ticket-pagination-filters
date: 2026-07-10
title: Pagination des tickets et filtres enregistrés
author: Tim Falken

Les listes de tickets affichent désormais au maximum **20 tickets par page**, avec une navigation au-dessus et en dessous de la liste. Les liens de pagination conservent les filtres, le terme de recherche et les autres paramètres d'URL. Après une recherche ou un filtrage, la pagination est recalculée sur les résultats filtrés.

Les filtres enregistrés se chargent à nouveau correctement lorsque vous accédez directement à `admin.php` ou revenez à l'aperçu ICT ou Tous les tickets via le menu de navigation.

---
id: 2026-07-08-translation-assignment-fixes
date: 2026-07-08
title: Traductions et attribution des tickets
author: Tim Falken

Pour les tickets traduits, vous ne voyez que le texte traduit. L'original est disponible via **Afficher l'original**, ou pendant le chargement de la traduction.

Les tickets créés via l'API (comme les demandes d'accès automatiques) sont immédiatement attribués à un administrateur ICT disponible. Les tickets existants sans responsable sont attribués automatiquement et silencieusement lors du chargement ou de la recherche.

---
id: 2026-07-08-ticket-ux-upload-fixes
date: 2026-07-08
title: Recherche, auto-attribution et téléversements
author: Tim Falken

Le champ de recherche de l'aperçu des tickets se met à jour en arrière-plan sans recharger la page, ce qui vous permet de continuer à taper.

Vous pouvez toujours vous attribuer un ticket **à vous-même**, même si vous êtes marqué absent ou si les règles de catégorie le bloqueraient autrement.

Les téléversements très volumineux (comme les MP4) affichent désormais une erreur claire au lieu de couper la session. Les images intégrées se chargent plus fiablement lorsqu'il y en a plusieurs dans un message.

---
id: 2026-07-08-all-tickets-tab
date: 2026-07-08
title: Onglet Tous les tickets et tickets privés
author: Tim Falken

Les utilisateurs normaux disposent d'un nouvel onglet **Tous les tickets** avec un aperçu des tickets résolus. Les tickets y sont en lecture seule : vous pouvez les consulter mais pas envoyer de messages ni modifier les données. L'aperçu propose les mêmes filtres que la vue d'ensemble ICT (catégorie, recherche, responsable).

Les administrateurs ICT peuvent marquer un ticket comme **Privé** dans la vue d'ensemble ICT via une case à cocher sur le ticket. Les tickets privés n'apparaissent jamais dans l'onglet Tous les tickets.

Dans la vue d'ensemble ICT, Tous les tickets et Mes tickets, une icône 🔗 apparaît à gauche du numéro de ticket. Elle copie partout le même lien (`index.php?open=…`). À l'ouverture, vous arrivez au bon endroit : **vos propres tickets** dans Mes tickets, les **admins** sinon dans la vue ICT, les **autres utilisateurs** sur les tickets publics résolus dans Tous les tickets.

---
id: 2026-06-23-message-textarea-grow
date: 2026-06-23
title: Le champ de texte s'agrandit avec votre message
author: Tim Falken

Lors de la création d'un nouveau ticket ou d'une réponse à un ticket existant, le champ de texte s'agrandit automatiquement pendant que vous tapez. Vous n'avez plus besoin de faire défiler dans le champ ni de le redimensionner manuellement.

---
id: 2026-06-23-admin-ticket-improvements
date: 2026-06-23
title: Gestion des tickets et statistiques
author: Tim Falken

Les administrateurs ICT peuvent modifier le titre d'un ticket via un bouton en haut du ticket, comme pour la catégorie. Les cartes titre, dates, priorité, utilisateurs et catégorie sont plus compactes et disposées dans une grille plus claire.

La page statistiques affiche des compteurs supplémentaires pour les tickets en attente (commande, utilisateur, tiers). Dans le tableau par demandeur, vous voyez combien de tickets chaque personne a soumis.

---
id: 2026-06-19-user-display-names
date: 2026-06-19
title: Noms à la place des adresses e-mail
author: Tim Falken

Lorsque c'est possible, vous voyez maintenant le vrai nom d'un utilisateur au lieu de son adresse e-mail — par exemple pour les demandeurs, les responsables, les messages et les statistiques. Survolez pour voir l'adresse e-mail. Les noms connus sont mis en cache localement pour garder l'aperçu rapide.

---
id: 2026-06-19-changelog-tab
date: 2026-06-19
title: Onglet Changelog
author: Tim Falken

Les administrateurs voient les nouveautés d'Asclepius. Les mises à jour non lues sont repliées ; ouvrez un élément pour le lire. Les éléments lus peuvent être réaffichés via le bouton en bas.

---
id: 2026-06-19-attachments
date: 2026-06-19
title: Pièces jointes dans les messages
author: Tim Falken

Vous pouvez supprimer les pièces jointes avant d'envoyer un message. Les images du presse-papiers sont placées automatiquement dans le texte ; les autres fichiers peuvent être insérés via un bouton. Les pièces jointes intégrées apparaissent comme un bloc distinct dans le texte.

---
id: 2026-06-19-admin-preferences
date: 2026-06-19
title: Préférences e-mail et nouveau statut
author: Tim Falken

Les administrateurs ICT peuvent choisir pour quels événements ils reçoivent un e-mail. Un nouveau statut « En attente de tiers » a été ajouté pour les tickets en attente d'une partie externe.

---
id: 2026-06-18-category-change
date: 2026-06-18
title: Modifier la catégorie du ticket
author: Tim Falken

Les administrateurs peuvent modifier la catégorie d'un ticket existant, avec réassignation optionnelle à un autre responsable. Le demandeur et tout nouveau responsable reçoivent une notification.

---
id: 2026-06-17-performance
date: 2026-06-17
title: Vue d'ensemble des tickets plus rapide
author: Tim Falken

Le chargement et l'actualisation des longues listes de tickets sont optimisés : les messages ne se chargent qu'à l'ouverture, le polling envoie moins de données et la base de données utilise des requêtes plus efficaces.

---
id: 2026-06-16-session-uploads
date: 2026-06-16
title: Sessions plus longues et meilleurs téléversements
author: Tim Falken

Les sessions restent actives plus longtemps pendant le travail sur les tickets. Les pièces jointes multiples ne sont plus écrasées lors d'une nouvelle sélection de fichiers, et la session est vérifiée avant l'envoi des formulaires.

---
id: 2026-05-13-ticket-search
date: 2026-05-13
title: Recherche de tickets
author: Omer Pesket

La vue d'ensemble ICT dispose d'un champ de recherche pour filtrer les tickets par titre, demandeur et autres champs.

---
id: 2026-05-07-translations
date: 2026-05-07
title: Traduction automatique
author: Tim Falken

Les messages de ticket peuvent être traduits automatiquement dans la langue du lecteur. La prise en charge de plusieurs moteurs de traduction a été préparée.

---
id: 2026-05-05-template-tickets
date: 2026-05-05
title: Tickets modèle et cases à cocher
author: Tim Falken

Les tickets modèle facilitent la création de demandes standard. Les messages prennent en charge des cases à cocher interactives. Les catégories sur la page des paramètres peuvent être réordonnées.

---
id: 2026-05-05-timezone
date: 2026-05-05
title: Fuseau horaire et dates
author: Omer Pesket

Les dates et heures dans l'application et l'API suivent désormais de manière cohérente le fuseau horaire configuré.

---
id: 2026-04-30-multi-user-keys
date: 2026-04-30
title: Participants multiples et symboles clavier
author: Tim Falken

Les tickets peuvent avoir plusieurs participants. Les champs de texte permettent d'insérer rapidement des touches et symboles via un menu de sélection.

---
id: 2026-04-29-file-previews
date: 2026-04-29
title: Aperçu des pièces jointes
author: Tim Falken

Les images et divers types de fichiers peuvent être consultés directement dans le ticket sans téléchargement, y compris les miniatures et les aperçus de documents.
