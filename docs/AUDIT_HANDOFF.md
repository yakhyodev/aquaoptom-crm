# AQUAOPTOM CRM — AUDIT YAKUNI VA KEYINGI ISHLAR

Sana: 2026-10-04. Foydalanuvchi limit tiklangach ushbu agentga davom etishni ruxsat qildi. Oldingi pause topshirig‘i bekor qilindi. Audit boshidan qayta bajarilmadi.

## Joriy source va dalil

Private repo: https://github.com/yakhyodev/aquaoptom-crm. Source commit: `7425c8278195367430693ee659196d848c8e7886`. [Yakuniy CI](https://github.com/yakhyodev/aquaoptom-crm/actions/runs/37199142828) **SUCCESS: 3/3 jobs,244backend tests/1536assertions/0errors/0failures/0skipped,21Flutter tests/analyze0issues,actual Docker build/runtime,dependency audits va Reverb PASS.** Dalillar [audit hujjatida](PRODUCTION_AUDIT.md) saqlangan. Hujjat/checkpoint commit source test qilingan commitdan keyin bo‘lishi mumkin; source fayllari o‘zgarmasa test dalili shu source revisionga tegishli bo‘lib qoladi.

Oxirgi staging fix: simulation faqat local/testing/staging + alohida test/testing/staging nomli DBda ishlaydi. Production yoki nomi mos bo‘lmagan DBda birinchi yozuvdan oldin rad etiladi. “Simulation passed” live staging/production approval emas. Regressionlar ReleaseProfileVerificationTestda.

Tasdiqlangan qolgan fixlar va barcha25bosqich qamrovi [PRODUCTION_AUDIT.md](PRODUCTION_AUDIT.md)da. Real foydalanishga chiqarish yo‘riqnomasi [PRODUCTION_HANDOVER.md](PRODUCTION_HANDOVER.md)da. Eski QURILISH_HOLATI DONE/GO yozuvlari tarixiy; yuqoridagi audit override amaldagi holatdir.

## Qolgan ishlar — tashqi release gate’lar

1. Server va domen/HTTPS tashkil qilish; production secretlar, secure cookies, PostgreSQL16/Redis7, queue/outbox/scheduler/Reverb va monitoringni real muhitda tekshirish.
2. Owner-controlled private Android keystore bilan tested source + real API endpointdan yangi signed APK build qilish. Mavjud eski APK `apksigner verify`dan o‘tmagan. Hash mosligi signature isboti emas. Real install/update va signing identity backupini tekshirish.
3. Haqiqiy PC/PWA va Androidda offline ikki qurilma ajratmasi, duplicate tap, reconnect, missing ACK, app kill/restart/upgrade, retained ACK recovery va inventory freeze/ACK acceptance sinovlari.
4. Haqiqiy Telegram token/webhook/private-chat bilan ownership, nasiya/to‘lov va bildirishnoma pilotini bajarish. Mavjud bot testlari mocked API bilan ishlaydi.
5. Offsite backup/key recovery va isolated restore parityni real infratuzilmada bajarish; real RPO nusxa yoshi va yo‘qotish oynasi, RTO esa xizmat tiklanishigacha vaqt bilan o‘lchanadi.
6. Prompt24 real staging acceptance, Prompt25 real HTTPS deploy/current signed APK/owner reconciliation/handover. Haqiqiy opening import shu gate’lar tugagandan keyin boshlanadi.

## Davom ettirish qoidalari

- **Verdict: NOT READY FOR PRODUCTION**. Server/domain mavjud emasligi foydalanuvchi tomonidan tasdiqlangan; yuqoridagi dalillar bajarilmaguncha 100%DONE deb belgilamang.
- Full suites GitHub Actions’da; Serena targeted symbols/checkpoint, Context7 faqat framework behavior noaniq bo‘lsa. Testlarni skip qilmang; arxitekturani qayta yozmang.
- Local `aquaoptom_prod` profil read-only tekshirildi: PG16.4/62tables/19migrations/zero businessdata. Audit real production bazani o‘zgartirmadi yoki haqiqiy Telegram xabarlarini yubormadi.
- Workspace `D:\Project\CRM`, PowerShell. PHP8.5; Flutter/Dart `D:\Program languages\flutter\bin`; Pint backend workdirda `php vendor/bin/pint --dirty --format agent`.
- Serena helper `%TEMP%\aquaoptom-audit-serena.py`; SDK `%APPDATA%\uv\tools\serena-agent\Scripts\python.exe -X utf8`, JSON tool array stdin. Project checkpoint `.serena/memories/production_audit_checkpoint.md`. Global Codex memoryga yozilmadi.
- Production host/secrets/signing identity berilmagan. Keyingi bosqichda zarur infratuzilma va foydalanuvchi ruxsatini aniqlab, konkret hosting/device ishiga o‘ting.
