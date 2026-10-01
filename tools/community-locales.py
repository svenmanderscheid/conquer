"""Merge the community feature's authored copy into the shared locale catalogs."""
import json,re,subprocess
from pathlib import Path
root=Path(__file__).resolve().parents[1]
copy={
'overview':('Overview','Übersicht','Vue d’ensemble'),
'conversations':('Conversations','Gespräche','Conversations'),
'friends':('Friends','Freunde','Amis'),
'news':('News','Neuigkeiten','Actualités'),
'settings':('Settings','Einstellungen','Paramètres'),
'welcome':('Your community','Deine Gemeinschaft','Votre communauté'),
'intro':('Meet fellow rulers, stay in touch and plan your next adventure together.','Finde Mitspieler, bleibe in Kontakt und plant euer nächstes Abenteuer.','Rencontrez des souverains, gardez le contact et préparez votre prochaine aventure.'),
'discord_join':('Join our Discord','Unserem Discord beitreten','Rejoindre notre Discord'),
'unread':('Unread messages','Ungelesene Nachrichten','Messages non lus'),
'requests':('Friend requests','Freundschaftsanfragen','Demandes d’amitié'),
'request_pending':('Request pending','Anfrage ausstehend','Demande en attente'),
'alliance_planning':('Alliance planning','Allianzplanung','Organisation de l’alliance'),
'alliance_tools':('Alliance tools','Allianzwerkzeuge','Outils de l’alliance'),
'find_alliance':('Find an alliance','Allianz finden','Trouver une alliance'),
'world_chat':('World chat','Weltchat','Discussion mondiale'),
'alliance_chat':('Alliance chat','Allianzchat','Discussion d’alliance'),
'private_chat':('Private chat','Privatchat','Discussion privée'),
'letters':('Mail','Post','Courrier'),
'help':('Help and guides','Hilfe und Anleitungen','Aide et guides'),
'recent_conversations':('Recent conversations','Letzte Gespräche','Conversations récentes'),
'latest_news':('Latest news','Aktuelle Neuigkeiten','Dernières actualités'),
'no_conversations':('No conversations yet. Find a player in Friends to get started.','Noch keine Gespräche. Suche unter Freunde nach einem Spieler.','Aucune conversation. Recherchez un joueur dans Amis pour commencer.'),
'start_conversation':('Start a conversation','Gespräch beginnen','Démarrer une conversation'),
'message':('Message','Nachricht','Message'),
'accept':('Accept','Annehmen','Accepter'),
'decline':('Decline','Ablehnen','Refuser'),
'remove_friend':('Remove friend','Freund entfernen','Retirer cet ami'),
'cancel_request':('Cancel request','Anfrage zurückziehen','Annuler la demande'),
'add_friend':('Add friend','Freund hinzufügen','Ajouter un ami'),
'more':('More','Mehr','Plus'),
'block':('Block','Blockieren','Bloquer'),
'unblock':('Unblock','Blockierung aufheben','Débloquer'),
'report':('Report','Melden','Signaler'),
'find_players':('Find players','Spieler finden','Trouver des joueurs'),
'search_placeholder':('Player name','Spielername','Nom du joueur'),
'search':('Search','Suchen','Rechercher'),
'search_results':('Search results','Suchergebnisse','Résultats'),
'no_results':('No matching players in this world.','Keine passenden Spieler in dieser Welt.','Aucun joueur correspondant dans ce monde.'),
'no_requests':('No new friend requests.','Keine neuen Freundschaftsanfragen.','Aucune nouvelle demande d’amitié.'),
'no_friends':('Your friends will appear here.','Deine Freunde erscheinen hier.','Vos amis apparaîtront ici.'),
'sent_requests':('Sent requests','Gesendete Anfragen','Demandes envoyées'),
'private_messages':('Who can send you private messages?','Wer darf dir private Nachrichten senden?','Qui peut vous envoyer des messages privés ?'),
'privacy_everyone':('Everyone','Alle Spieler','Tout le monde'),
'privacy_friends':('Friends only','Nur Freunde','Amis uniquement'),
'privacy_alliance':('Alliance members only','Nur Allianzmitglieder','Membres de l’alliance uniquement'),
'privacy_nobody':('Nobody','Niemand','Personne'),
'notifications':('Channel notifications','Kanalbenachrichtigungen','Notifications des canaux'),
'notify_all':('All messages','Alle Nachrichten','Tous les messages'),
'notify_mentions':('Mentions only','Nur Erwähnungen','Mentions uniquement'),
'notify_off':('Muted','Stumm','Silencieux'),
'save':('Save','Speichern','Enregistrer'),
'blocked_players':('Blocked players','Blockierte Spieler','Joueurs bloqués'),
'no_blocks':('You have not blocked anyone.','Du hast niemanden blockiert.','Vous n’avez bloqué personne.'),
'navigation':('Community sections','Community-Bereiche','Rubriques de la communauté'),
'refresh':('Refresh','Aktualisieren','Actualiser'),
'news_intro':('Official game updates are also available here, without a Discord account.','Offizielle Spielneuigkeiten findest du auch hier, ohne Discord-Konto.','Retrouvez aussi les annonces officielles ici, sans compte Discord.'),
'no_news':('No announcements yet.','Noch keine Ankündigungen.','Aucune annonce pour le moment.'),
'loading':('Loading your community…','Gemeinschaft wird geladen …','Chargement de votre communauté…'),
'report_player':('Report a player','Spieler melden','Signaler un joueur'),
'reason':('Reason','Grund','Motif'),
'reason_harassment':('Harassment','Belästigung','Harcèlement'),
'reason_spam':('Spam','Spam','Spam'),
'reason_cheating':('Cheating','Betrug','Triche'),
'reason_inappropriate':('Inappropriate content','Unangemessener Inhalt','Contenu inapproprié'),
'reason_other':('Other','Sonstiges','Autre'),
'details':('Details','Einzelheiten','Détails'),
'send_report':('Send report','Meldung senden','Envoyer le signalement'),
'report_sent':('Your report has been sent to the moderation team.','Deine Meldung wurde an die Moderation gesendet.','Votre signalement a été envoyé à la modération.'),
'admin_required':('Administrator access required.','Administratorzugang erforderlich.','Accès administrateur requis.'),
'invalid_news':('Please provide a valid title, message and world.','Bitte gib einen gültigen Titel, Nachrichtentext und eine Welt an.','Veuillez indiquer un titre, un texte et un monde valides.'),
'news_saved':('Announcement saved.','Ankündigung gespeichert.','Annonce enregistrée.'),
'sign_in':('Please sign in.','Bitte melde dich an.','Veuillez vous connecter.'),
'moderation':('Community moderation','Community-Moderation','Modération de la communauté'),
'reports':('Player reports','Spielermeldungen','Signalements'),
'no_reports':('No reports in this world.','Keine Meldungen in dieser Welt.','Aucun signalement dans ce monde.'),
'status':('Status','Status','Statut'),
'status_new':('New','Neu','Nouveau'),
'status_reviewing':('Reviewing','In Prüfung','En cours'),
'status_resolved':('Resolved','Bearbeitet','Résolu'),
'status_dismissed':('Dismissed','Abgewiesen','Rejeté'),
'internal_note':('Internal note','Interne Notiz','Note interne'),
'active_bans':('Active chat restrictions','Aktive Chatsperren','Restrictions de discussion actives'),
'no_bans':('No active chat restrictions.','Keine aktiven Chatsperren.','Aucune restriction active.'),
'restrict_chat':('Restrict chat access','Chat sperren','Restreindre la discussion'),
'player_id':('Player ID','Spieler-ID','ID du joueur'),
'duration_minutes':('Duration in minutes','Dauer in Minuten','Durée en minutes'),
'lift_ban':('Lift restriction','Sperre aufheben','Lever la restriction'),
'publish_news':('Publish game news','Spielneuigkeiten veröffentlichen','Publier une annonce'),
'title':('Title','Titel','Titre'),
'body':('Message','Nachricht','Message'),
'published':('Published','Veröffentlicht','Publié'),
'draft':('Draft / hidden','Entwurf / verborgen','Brouillon / masqué'),
'recent_news':('Recent announcements','Letzte Ankündigungen','Annonces récentes'),
'world_log':('Latest world chat messages','Letzte Weltchatnachrichten','Derniers messages mondiaux'),
'reporter':('Reported by','Gemeldet von','Signalé par'),
'expires':('Expires (UTC)','Endet (UTC)','Expire (UTC)'),
'saved_notice':('Save announcement','Ankündigung speichern','Enregistrer l’annonce'),
}
catalogs={locale:json.loads((root/f'data/i18n/{locale}.json').read_text(encoding='utf-8-sig')) for locale in ['en','de','fr']}
for key,values in copy.items():
    for locale,value in zip(catalogs,values): catalogs[locale]['social.'+key]=value
for locale in catalogs:
    catalogs[locale]['nav.alliance-community']=catalogs[locale]['social.alliance_planning']
    catalogs[locale]['nav.alliance-tools']=catalogs[locale]['social.alliance_tools']
service=root/'src/Game/Community/SocialService.php'
if service.exists():
    chunk=service.read_text(encoding='utf-8-sig').split('public const TEXTS=[',1)[1].split('];',1)[0]
    for key,value in re.findall(r"'([^']+)'\s*=>\s*'((?:\\.|[^'])*)'",chunk):
        for locale in catalogs: catalogs[locale].setdefault('social.'+key,value.replace("\\'","'"))
for fragment in ['chat-i18n.json','alliance-i18n.json','alliance-i18n-locales.json','social-backend-i18n.json']:
    path=root/'artifacts/community-upgrade'/fragment
    if not path.exists():continue
    data=json.loads(path.read_text(encoding='utf-8-sig'))
    if 'en' in data:
        for locale in catalogs:catalogs[locale].update(data.get(locale,data['en']))
    else:
        for locale in catalogs:catalogs[locale].update(data)
# Complete the existing French reward-editor gap without replacing authored entries.
french_reward_editor={
    'matrix_view':'Comparer les récompenses',
    'matrix_items':'Objets de butin',
    'matrix_item':'Objet {number}',
    'matrix_hint':'Choisissez les objets dans les en-têtes de colonnes. Cliquez sur une quantité ou une probabilité pour modifier ce butin. — signifie aucun butin. Valeurs enregistrées avant les bonus ; les changements ne s’appliquent qu’après enregistrement.',
    'edit_drop':'Modifier {item} pour {source}',
    'add_drop_for':'Ajouter {item} à {source}',
    'add_drop':'+ Ajouter du butin',
    'drop_focus_note':'{source} · {item} : modifiez la quantité ou la probabilité ci-dessous, puis enregistrez les récompenses.',
    'drop_added_note':'{item} pour {source} : ajouté à vos saisies non enregistrées avec une probabilité de 0 %. Définissez la quantité et la probabilité, puis enregistrez les récompenses.',
    'drop_limit':'Cette source contient déjà 200 entrées de butin. Supprimez-en une avant d’en ajouter une autre.',
    'search_monsters':'Rechercher des monstres ou du butin',
    'search_monsters_hint':'Nom de monstre, nom d’objet ou numéro…',
    'search_sources':'Rechercher une source',
    'search_sources_hint':'Nom ou numéro…',
    'reset_filters':'Réinitialiser les filtres',
    'matrix_no_drop':'Aucun butin',
    'matrix_resource_hint':'Ressources directes par victoire ; celles des ralliements sont partagées. Les cristaux indiquent la quantité × et la probabilité. Les paquets de l’inventaire figurent dans Objets de butin.',
    'matrix_solo':'Solo',
    'matrix_rally':'Ralliement',
}
for key,value in french_reward_editor.items():catalogs['fr'].setdefault('admin.drops.'+key,value)
for locale,data in catalogs.items():
    (root/f'data/i18n/{locale}.json').write_text(json.dumps(data,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
print('Community catalogs merged:',len(copy),'hub entries plus available feature fragments.')
