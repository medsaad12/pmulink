/** Projets par défaut dans la barre d’outils, regroupés par département. */

/**
 * @typedef {{
 *   id: string
 *   label: string
 *   meteo: 'soleil' | 'brouillard' | 'difficile'
 * }} Department
 */

/** @type {Department[]} */
export const DEPARTMENTS = [
  { id: 'finance', label: 'Finance', meteo: 'soleil' },
  { id: 'commerciale', label: 'Commerciale', meteo: 'brouillard' },
  { id: 'production', label: 'Production', meteo: 'difficile' },
  { id: 'dnt', label: 'DNT', meteo: 'brouillard' },
]

/**
 * @typedef {{
 *   name: string
 *   color: string
 *   departmentId: string
 *   projectStatus: 'bon' | 'vigilance' | 'critique' | 'cloture' | 'archivee'
 *   progress: number
 *   faitsMarquants: string[]
 *   commentaires: string[]
 * }} ProjectTemplate
 */

/** @type {ProjectTemplate[]} */
export const PROJECT_ITEMS = [
  // —— Finance (3)
  {
    name: 'Optimisation complète du cycle d’encaissement et de réconciliation multi-moyens',
    color: '#059669',
    departmentId: 'finance',
    projectStatus: 'bon',
    progress: 100,
    faitsMarquants: [
      'Encaissement et réconciliation des paiements multi-moyens.',
      'Conformité PCI et journalisation des opérations sensibles.',
      'Rapprochement bancaire assisté pour la comptabilité.',
      'Gestion des remboursements et des impayés selon les règles métier.',
      "Reporting des encours et des échéances à l'attention du siège.",
    ],
    commentaires: [
      'Besoin de préciser le libellé bancaire sur les relevés clients.',
      "Attente d'un connecteur supplémentaire pour une néobanque partenaire.",
      'Les équipes support veulent un code motif plus fin sur les rejets.',
      "Documentation juridique à mettre à jour suite à la dernière réforme.",
      "Demande d'un environnement de simulation pour les formations internes.",
    ],
  },
  {
    name: 'Pilotage en temps réel de la trésorerie consolidée avec scénarios de stress',
    color: '#1d4ed8',
    departmentId: 'finance',
    projectStatus: 'critique',
    progress: 22,
    faitsMarquants: [
      'Vue consolidée des soldes et des lignes de trésorerie par entité.',
      'Prévisions de trésorerie sur 13 semaines avec scénarios sensibles.',
      'Alertes sur les seuils de découvert et les engagements à venir.',
      'Intégration des flux bancaires et des opérations intercos.',
      'Tableaux de bord partagés avec la direction financière.',
    ],
    commentaires: [
      'Les filiales attendent un export Excel conforme au modèle du groupe.',
      'Délai de rafraîchissement des soldes à afficher clairement en bannière.',
      "Demande d'un mode lecture seule pour les contrôleurs externes.",
      'Paramétrage des seuils d’alerte encore trop dispersé entre les sites.',
      'Tests de charge prévus avant la clôture annuelle.',
    ],
  },
  {
    name: 'Mise en conformité IFRS avec traçabilité détaillée des ajustements comptables',
    color: '#7c3aed',
    departmentId: 'finance',
    projectStatus: 'vigilance',
    progress: 58,
    faitsMarquants: [
      'Cartographie des écarts entre référentiels locaux et IFRS.',
      'Workflow de validation des écritures d’ajustement trimestrielles.',
      'Historique des versions de normes et des impacts sur les comptes.',
      'Contrôles croisés entre consolidation et reporting statutaire.',
      'Piste d’audit exportable pour les commissaires aux comptes.',
    ],
    commentaires: [
      'Les équipes demandent des exemples commentés pour les contrats complexes.',
      'Lenteur occasionnelle sur les rapprochements inter-périodes.',
      "Besoin d'un glossaire métier aligné sur le dernier guide IFRS.",
      'Formation courte demandée pour les nouveaux arrivants en consolidation.',
      'Synchronisation avec le référentiel produit comptable à clarifier.',
    ],
  },

  // —— Commerciale (3)
  {
    name: 'Orchestration de bout en bout du traitement des commandes clients et litiges',
    color: '#db2777',
    departmentId: 'commerciale',
    projectStatus: 'vigilance',
    progress: 67,
    faitsMarquants: [
      'Suivi des commandes de bout en bout pour les équipes sales et logistique.',
      'Statuts standardisés (saisie, validation, expédition, clôture).',
      'Relances automatiques en cas de blocage sur une étape.',
      'Intégration avec la facturation et les stocks disponibles.',
      'Tableaux de synthèse par canal et par segment client.',
    ],
    commentaires: [
      "Besoin d'ajouter un motif de litige directement sur la commande.",
      'Les filtres par période mériteraient un raccourci “ce mois-ci”.',
      'Remontée occasionnelle de doublons sur les imports CSV.',
      "Demande d'historique plus lisible pour les gros comptes.",
      'Tests de charge prévus avant la prochaine campagne promotionnelle.',
    ],
  },
  {
    name: 'Suivi stratégique du pipeline d’affaires et fiabilisation des prévisions commerciales',
    color: '#ea580c',
    departmentId: 'commerciale',
    projectStatus: 'bon',
    progress: 95,
    faitsMarquants: [
      'Vue Kanban des opportunités par étape et par responsable commercial.',
      'Calcul automatique de la probabilité pondérée sur le prévisionnel.',
      'Lien bidirectionnel avec les devis et les commandes clients.',
      'Rappels sur les actions à mener avant date de clôture prévue.',
      'Exports vers le CRM siège pour le comité de direction.',
    ],
    commentaires: [
      'Les commerciaux terrain demandent un mode hors-ligne partiel.',
      'Doublons détectés lors de la fusion de comptes prospects.',
      "Ajout d'un champ « concurrent cité » souhaité par le marketing.",
      'Les rapports PDF mériteraient le logo filiale automatique.',
      'Campagne e-mailing intégrée encore en phase pilote.',
    ],
  },
  {
    name: 'Déploiement du portail partenaires sécurisé pour distributeurs et intégrateurs',
    color: '#0f766e',
    departmentId: 'commerciale',
    projectStatus: 'critique',
    progress: 12,
    faitsMarquants: [
      'Portail sécurisé pour les distributeurs et intégrateurs agréés.',
      'Téléchargement des plaquettes tarifaires et des conditions générales.',
      'Espace de tickets pour les demandes commerciales prioritaires.',
      'Tableau des objectifs trimestriels et du niveau de réalisation.',
      'Notifications lors des mises à jour des programmes partenaires.',
    ],
    commentaires: [
      'Première vague de tests utilisateurs prévue après les vacances.',
      "Manque d'une FAQ vidéo pour les parcours d'accès.",
      'Les partenaires souhaitent un récapitulatif mensuel par e-mail.',
      'Clarifier la politique de rétention des documents uploadés.',
      'Compatibilité navigateurs anciens encore à valider.',
    ],
  },

  // —— Production (3)
  {
    name: 'Planification transverse des charges projets et suivi du réalisé opérationnel',
    color: '#4f46e5',
    departmentId: 'production',
    projectStatus: 'vigilance',
    progress: 44,
    faitsMarquants: [
      'Planification des charges et des jalons projet sur une timeline unique.',
      'Saisie des temps passés par activité et par client.',
      'Comparaison prévisionnel versus réalisé en quelques clics.',
      'Alertes sur les dérives de planning ou les surcharges.',
      'Exports compatibles avec les outils de pilotage existants.',
    ],
    commentaires: [
      "Les managers aimeraient verrouiller la semaine passée après validation RH.",
      'Bug mineur sur le fuseau horaire pour les collaborateurs offshore.',
      'Demande de vue “calendrier” pour les équipes terrain.',
      'Formation express demandée sur les codes projet multi-affaires.',
      'Intégration Outlook à tester sur le prochain lot de licences.',
    ],
  },
  {
    name: 'Digitalisation des ordres de fabrication avec contrôle atelier et performance OEE',
    color: '#e11d48',
    departmentId: 'production',
    projectStatus: 'critique',
    progress: 8,
    faitsMarquants: [
      'Génération des OF à partir des besoins nets calculés par le MRP.',
      'Suivi des lancements, des arrêts et des rebuts atelier.',
      'Lien avec la nomenclature et les gammes opératoires versionnées.',
      'Impression d’étiquettes et de fiches poste conformes aux normes.',
      'Indicateurs OEE par ligne et par équipe.',
    ],
    commentaires: [
      'Les chefs d’atelier demandent un mode saisie vocale expérimental.',
      'Import des temps machine encore instable sur une ligne pilote.',
      "Besoin d'un double contrôle qualité avant validation des quantités.",
      'Documentation des arrêts planifiés à enrichir.',
      'Tests utilisateurs avec deux sites de production cette semaine.',
    ],
  },
  {
    name: 'Renforcement du contrôle qualité lot avec traçabilité matière et blocage automatique',
    color: '#16a34a',
    departmentId: 'production',
    projectStatus: 'vigilance',
    progress: 73,
    faitsMarquants: [
      'Plans de contrôle associés à chaque référence et à chaque fournisseur.',
      'Saisie mobile des constats sur ligne avec photos annotées.',
      'Traçabilité complète du lot jusqu’aux matières premières.',
      'Statistiques de défauts par cause et par poste de contrôle.',
      'Blocage automatique des expéditions si non-conformité majeure.',
    ],
    commentaires: [
      'Les opérateurs souhaitent un thème sombre pour les écrans de ligne.',
      'Synchronisation avec le LIMS encore en cours de paramétrage.',
      "Demande d'un export statistique vers Excel pour le service méthodes.",
      'Quelques lenteurs sur la galerie photo lors de pics de production.',
      'Formation recyclage prévue pour la prochaine rotation d’équipe.',
    ],
  },

  // —— DNT — Direction des nouvelles technologies (3)
  {
    name: 'Orchestration unifiée des parcours métiers inter-applications pour les équipes terrain',
    color: '#c026d3',
    departmentId: 'dnt',
    projectStatus: 'vigilance',
    progress: 41,
    faitsMarquants: [
      'Orchestration des parcours métiers sur plusieurs applications.',
      "Point d'entrée unique pour l'utilisateur final.",
      'Réduction des allers-retours entre les outils internes.',
      'Traçabilité des étapes clés du flux opérationnel.',
      "Alignement avec la cartographie des processus de l'entreprise.",
    ],
    commentaires: [
      "Les équipes attendent une version mobile du tableau de bord.",
      'Session parfois longue au premier chargement du matin.',
      "Demande d'un export PDF des étapes validées.",
      'Formation courte demandée pour les nouveaux profils support.',
      'Synchronisation avec le référentiel produit à clarifier côté MOA.',
    ],
  },
  {
    name: 'Centralisation intelligente des notes opérationnelles et comptes rendus collaboratifs',
    color: '#0284c7',
    departmentId: 'dnt',
    projectStatus: 'bon',
    progress: 100,
    faitsMarquants: [
      'Centralisation des notes et comptes rendus par dossier ou par équipe.',
      'Recherche plein texte sur les contenus rédigés.',
      'Partage contrôlé par rôles et par espaces de travail.',
      'Modèles de prise de note pour les réunions récurrentes.',
      'Liens profonds vers les projets ou tickets associés.',
    ],
    commentaires: [
      "Possibilité d'exporter une note en Markdown souhaitée par la R&D.",
      'Parfois lenteur lors du collage de très longues captures.',
      "Ajout d'un correcteur orthographique côté navigateur suffit pour l'instant.",
      'Demande de notifications silencieuses pour les mises à jour mineures.',
      "Clarifier la rétention des brouillons après 90 jours d'inactivité.",
    ],
  },
  {
    name: 'Gouvernance documentaire avec versionnement, validation et publication des référentiels',
    color: '#ca8a04',
    departmentId: 'dnt',
    projectStatus: 'critique',
    progress: 19,
    faitsMarquants: [
      'Dépôt et versionnement des documents métier officiels.',
      'Workflow de validation et de publication des pièces.',
      'Indexation pour retrouver rapidement contrats et annexes.',
      "Gestion des droits d'accès par typologie de document.",
      'Historique des révisions consultable par les équipes audit.',
    ],
    commentaires: [
      "Limite de taille par fichier à communiquer plus clairement à l'upload.",
      "Demande d'aperçu rapide pour les fichiers Office sans téléchargement.",
      'Certaines corbeilles mériteraient une restauration en masse.',
      "Projet pilote en cours sur la signature électronique intégrée.",
      "Besoin d'étiquettes transverses entre plusieurs filiales.",
    ],
  },
]

export const DRAG_MIME = 'application/x-sticky-project+json'

/** Listes types pour un projet créé manuellement (5 entrées vides à compléter plus tard). */
export function defaultEmptyLists() {
  return {
    faitsMarquants: ['', '', '', '', ''],
    commentaires: ['', '', '', '', ''],
  }
}

const STICKY_DEFAULT_WIDTH = 320
const STICKY_LINE_PX = 28
/** Titre + interrupteur + pied (statut + progression) + marges (hors zone liste). */
const STICKY_BASE_PX = 176
/** Hauteur supplémentaire quand les deux listes sont vides. */
const STICKY_EMPTY_EXTRA = 52
const STICKY_MAX_H = 540

/**
 * Hauteur `scrollHeight` du champ titre pour ~1 ligne (padding + bordure + line-height).
 * Utilisé pour ajouter de la hauteur au post-it quand le titre passe sur plusieurs lignes.
 * @see `StickyNoteNode.vue` `.sticky-note__title`
 */
export const STICKY_TITLE_BASELINE_SCROLL_PX = 32

/**
 * Taille du post-it selon le nombre de lignes non vides (max des deux listes),
 * plus un surplus optionnel quand le titre dépasse une ligne (mesuré côté composant).
 * @param {number} [titleExtraScrollPx=0]  max(0, textarea.scrollHeight - STICKY_TITLE_BASELINE_SCROLL_PX)
 */
export function stickyDimensionsFromLists(faitsMarquants, commentaires, titleExtraScrollPx = 0) {
  const count = (arr) =>
    Array.isArray(arr)
      ? arr.filter((s) => String(s ?? '').trim().length > 0).length
      : 0
  const n = Math.max(count(faitsMarquants), count(commentaires))
  const extra = Math.max(0, Math.round(Number(titleExtraScrollPx) || 0))
  const base =
    n === 0
      ? STICKY_BASE_PX + STICKY_EMPTY_EXTRA
      : STICKY_BASE_PX + n * STICKY_LINE_PX + 24
  const h = Math.min(STICKY_MAX_H, base + extra)
  return {
    width: STICKY_DEFAULT_WIDTH,
    height: Math.max(176, Math.round(h)),
  }
}

/**
 * Valeur métier / stockée → statut canonique du post-it.
 * @param {unknown} raw
 * @returns {'bon' | 'vigilance' | 'critique' | 'cloture' | 'archivee'}
 */
export function normalizeProjectStatus(raw) {
  if (
    raw === 'bon' ||
    raw === 'vigilance' ||
    raw === 'critique' ||
    raw === 'cloture' ||
    raw === 'archivee'
  )
    return raw
  if (raw === 'termine') return 'cloture'
  if (raw === 'cloturé' || raw === 'clôturé') return 'cloture'
  if (raw === 'archive' || raw === 'archivé' || raw === 'archivée') return 'archivee'
  if (raw === 'en-cours') return 'vigilance'
  if (raw === 'a-faire') return 'critique'
  return 'vigilance'
}

/**
 * Thème visuel du post-it : mêmes familles que le feu tricolore (StickyBoard `.sticky-board__meteo-lamp`).
 * Rouge / ambre / vert : stops alignés sur les lampes (radial → ici en linéaire pour la carte).
 * @type {Record<'bon' | 'vigilance' | 'critique' | 'cloture' | 'archivee', { bg: string, face: string }>}
 */
const STICKY_STATUS_THEME = {
  bon: {
    bg: '#16a34a',
    face: 'linear-gradient(142deg, #86efac 0%, #4ade80 28%, #16a34a 55%, #14532d 100%)',
  },
  vigilance: {
    bg: '#d97706',
    face: 'linear-gradient(142deg, #fde68a 0%, #f59e0b 32%, #d97706 58%, #92400e 100%)',
  },
  critique: {
    bg: '#b91c1c',
    face: 'linear-gradient(142deg, #fca5a5 0%, #ef4444 38%, #b91c1c 62%, #7f1d1d 100%)',
  },
  cloture: {
    bg: '#15803d',
    face: 'linear-gradient(142deg, #86efac 0%, #22c55e 35%, #15803d 65%, #14532d 100%)',
  },
  archivee: {
    bg: '#64748b',
    face: 'linear-gradient(142deg, #cbd5e1 0%, #94a3b8 25%, #64748b 55%, #475569 80%, #1e293b 100%)',
  },
}

/**
 * @param {unknown} rawStatus
 */
export function stickyNoteThemeFromStatus(rawStatus) {
  const s = normalizeProjectStatus(rawStatus)
  return STICKY_STATUS_THEME[s] ?? STICKY_STATUS_THEME.vigilance
}

/**
 * Couleur d’ancrage (hex) pour `--sticky-bg` et dérivés (donut, mix).
 * @param {unknown} rawStatus
 * @returns {string}
 */
export function stickyNotePaperColorFromStatus(rawStatus) {
  return stickyNoteThemeFromStatus(rawStatus).bg
}

/**
 * Dégradé façade du post-it (propriété `background` complète).
 * @param {unknown} rawStatus
 * @returns {string}
 */
export function stickyNoteFaceGradientFromStatus(rawStatus) {
  return stickyNoteThemeFromStatus(rawStatus).face
}
