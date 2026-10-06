# Entry, quests and welcome rewards reference

Reference: [League of Kingdoms – First Time User Experience](https://www.youtube.com/watch?v=bs4Rdh-yw-0), including the user's [4:42 timestamp](https://youtu.be/bs4Rdh-yw-0?t=282). Reviewed on 6 October 2026 using the public video, sampled frames throughout, focused full-resolution frames around the quest/event panels, and its automatic English transcript. Analysis copies are under ignored `output/video-reference/`; they are not game assets.

Observed interactions:

- Around 1:50–3:50: adviser portrait over the actual dimmed city; manually advanced dialogue, skip, build/train guidance.
- 4:18–4:42: main/side quests, explicit claim rewards, target links and badges; a training speedup follows.
- 5:20–6:55: world search, monster attack, return and hospital guidance.
- 8:35: daily quests with activity chests at 20/40/60/80/100 points.
- 8:40–8:45: event categories with progress, reward milestones and newcomer login support.

Adaptation uses the existing painted artwork, warm beige/violet UI, shared language system and authoritative server state. It adds a short replayable adviser introduction, startup phase text, separated Main/Daily quest presentation, seven distinct visit-day rewards and six growth milestones inside the existing Events screen. Reward collection is explicit and uses saved command receipts for interrupted replies.

Existing construction/training prerequisites, queue speedups, monster search/attacks, hospitals, inventory, the eight daily quests, invasions and conquest events remain their existing implementations. The reference's timed training rankings were not recreated from its brief display: its scoring and settlement rules are not established by the video. The existing conquest ranking and event calendar remain available. The welcome campaign intentionally keeps rewards available and does not reset missed visit days.

The public video's artwork and audio are not shipped with the game. No blockchain, wallet or payment integration is added. These changes are local source changes; deployment and the additive welcome-event migration are separate rollout steps.

See `APP_START_SCREEN.md`, `BEGINNER_GUIDE.md` and `WELCOME_EVENT.md` for the contracts and verification. This change preserves the committed eight-quest daily baseline. A separate working-tree extension adds thirteen daily quests and activity chests; its implementation and tests are not included in this change.

Integration verification on 6 October 2026 used the shared development working tree, which also contained the separate daily-activity extension. Startup/intro, quest, daily-activity and welcome-event browser suites passed in the isolated main app across desktop, narrow portrait and short landscape. Backend checks passed: 149 welcome-event, 106 starter-reward and 144 daily-activity checks. The daily-activity suite and its 144 checks are evidence for that separate extension, not part of this commit. The reward catalog, startup lifecycle and beginner destination selection checks also passed. The scoped commit preserves the eight-quest baseline, and its quest browser suite derives daily counts from server state. These working-tree results do not by themselves verify the isolated commit; release validation must test that exact tree. No player database or deployed installation was changed by these checks; physical-device verification remains open.

The exact scoped release tree was then exported and verified independently on top of commit 02c93e97. All 149 welcome-event and 106 starter-reward checks passed in disposable databases. The welcome-event browser suite passed five screen sizes and persisted lost-response recovery; the quest suite passed six screen sizes and real claims; the intro suite passed five screen sizes, replay, navigation and true loading state. Startup lifecycle and beginner destination selection checks passed. No player data was changed.
