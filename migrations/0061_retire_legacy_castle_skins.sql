-- Removed cosmetics no longer appear in the catalogue. Keep accounts on a valid skin.
UPDATE kingdom_profiles SET city_skin='default'
WHERE city_skin IN ('forest','royal','flame','grandeur','empyrean','sunshine','heavenly','bastion','magisters','frost','crescent','darkness','sunbless','ape','cloud','fafnir','moonlight');
