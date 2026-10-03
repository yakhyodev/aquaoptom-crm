# Suv do‘koni CRM — productiongacha 25 ta ketma-ket prompt

Sana: 2026-10-03. Ushbu fayl qurish topshiriqlari to‘plami; promptlar hali bajarilmagan. Uni tayyorlash davomida ilova kodi, baza va deployment o‘zgartirilmadi.

Asosiy talab manbasi: [SUV_DOKONI_CRM_ARXITEKTURA_V2.md](/D:/Project/CRM/SUV_DOKONI_CRM_ARXITEKTURA_V2.md). Eski master hujjatlar bilan ziddiyatda yangi arxitektura va foydalanuvchining eng oxirgi ko‘rsatmasi ustun.

## Qanday foydalaniladi

1. 01-prompt matnini to‘liq nusxalab loyiha ochilgan chatga yuboring.
2. Agent shu bosqichni quradi, tegishli tekshiruvlarni bajaradi va ish holatini qayd etadi.
3. Majburiy qabul mezonlari o‘tgach navbatdagi promptni yuboring; hammasini birdan yubormang.
4. Check yiqilsa yangi bosqichga o‘tilmaydi: o‘sha bosqich tuzatiladi va tegishli tekshiruv qayta bajariladi.
5. Yangi chatda ham promptlar ishlaydi: ular arxitektura, ushbu fayl va oldingi ish holatini o‘qishni talab qiladi.
6. 01-bosqichda yaratiladigan QURILISH_HOLATI.md keyingi agentlar uchun davom ettirish manbasi bo‘ladi; u hozir bajarilgan hisobot sifatida yaratilmagan.

Bog‘liq ishlar bitta bosqich ichida backend → interfeys → tekshiruv tartibida bajariladi. Ayrim ish bajarilgani butun bosqich DONE degani emas.

## Barcha promptlar uchun umumiy qoidalar

- Faqat yuborilgan promptni bajaring; foydalanuvchi keyingisini yubormaguncha boshqa bosqichni avtomatik boshlamang.
- Ko‘lam: bitta suv/ichimlik do‘koni, bitta ombor, faqat butun dona. Blok/yashik, ko‘p ombor yoki ko‘p mijozli SaaS qo‘shilmaydi.
- Standart reliz: kompyuter va telefonda o‘rnatiladigan PWA, Android uchun Flutter, online Telegram bot. Native iOS, offline refund/xarajat/kirimni post qilish, partiya/FEFO va bank/fiskal integratsiyalar bu relizning majburiy qismi emas.
- Offline savdo, doimiy lokal saqlash, avtomatik sync va operation_id orqali bir martalik biznes ta’siri majburiy. Ochiq ilova internet qaytganda sync qiladi; OS/brauzer to‘xtatgan ilovada darhol background sync barcha platforma uchun kafolatlanmaydi.
- Parallel offline stock uchun qurilma ajratmasi, qat’iy kredit limiti yoqilsa kredit ajratmasi ishlatiladi. Fizik qoldiq, serverga ma’lum qoldiq va qurilma sotishi mumkin bo‘lgan dona farqlanadi.
- Keyingi mijoz to‘lovi umumiy signed hisobni kamaytiradi, mahsulot/checklarga majburiy taqsimlanmaydi. Avans yo‘qotilmaydi; savdo, foyda va cash aralashtirilmaydi.
- Mavjud kod ishlaydi deb taxmin qilmang. Arxitekturaga mos qismini dalil bilan ishlating, zid demo oqimini almashtiring. Secret, foydalanuvchi fayllari, mavjud ma’lumot va aloqasiz o‘zgarishlar saqlanadi.
- AGENTS.md va tegishli skill ko‘rsatmalarini o‘qing. Versiya, kutubxona/API va provider xususiyatini zarur joyda joriy rasmiy hujjatdan tekshiring.
- Test DB ajratilgan bo‘lsin. Financial/concurrency gate PostgreSQL’da; SQLite/fake test haqiqiy row-lock yoki tashqi integratsiya isboti emas.
- Moliyaviy amallarda rollback, duplicate/retry va parallel yozuv regressionlari zarur. Faqat implementatsiyani takrorlaydigan yuzaki test bilan cheklanmang; oddiy matn/layout o‘zgarishiga ortiqcha test yozmang.
- Yangi talabga zid eski demo testi sababini yozib yangilanadi; indamasdan skip/disable qilish mumkin emas. Baseline xato qayd etilishi uning tuzatilganini anglatmaydi.
- Kod ko‘rildi, test/build o‘tdi, lokal runtime, staging va actual production dalillari alohida; birini boshqasining isboti qilib ko‘rsatmang.
- Env, DB URL, Telegram token, parol va signing key javob/log/Gitga chiqarilmaydi. Tashqi jo‘natmalar testda fake; real sinov uchun alohida test bot/chat va staging.
- Migration reset/drop va restore real bazaga avtomatik qo‘llanmaydi. Test bazasini tozalashdan oldin uning manzili va nomini tekshiring.
- Kontrakt/lokal baza versiyalansin. Upgrade pending/ACK cheklar va navbatni o‘chirmaydi; eski pendingni serverda qabul qilish davri bo‘lsin.
- Commit/push alohida user ko‘rsatmasiga muvofiq. Staging faqat 24, production faqat 25-bosqich alohida yuborilganda chiqariladi. Hozirgi fayl yaratish deploy ruxsati yoki bajarilgan release emas.
- Majburiy check bajarilmasa DONE emas: CHECK_FAILED yoki NEEDS_INPUT va konkret sabab. Mustaqil shu bosqich ishlarini tugating; yetishmayotgan tashqi credential yoki biznes qarori zarur bo‘lsa konkret tayyor natijadan keyin aniqlashtiring.
- Har bosqich oxirida QURILISH_HOLATI.md yangilanadi: status, fayllar, migration/kontrakt, buyruq/natija, muhit, qoldiq ish va keyingi prompt. Secret kiritilmaydi.
- Yakuniy qisqa javob: nima qurildi, qanday tekshirildi, nima unverified, bosqich DONE bo‘ldimi. GO faqat 24-bosqich dalillariga asoslanadi.

## Bosqichlar xaritasi

| Qismlar | Natija |
|---|---|
| 01–03 | Xavfsiz muhit, login/ruxsat va responsive karkas |
| 04–06 | Katalog, idempotency, hisob daftarlari va opening qoldiqlar |
| 07–10 | Kirim, sotuv, ikki taraf qarzi, cash/xarajat/smena |
| 11–14 | Offline ajratma, sync server, PWA lokal savdo va reconnect |
| 15–18 | Ombor kalkulyatori, return/count, hisobot, dashboard/Admin/realtime |
| 19 | Telegram orqali ko‘rish va yozuvchi amallar |
| 20–21 | Flutter Android online/offline ilova va signing |
| 22–23 | Kompleks tekshiruv, release paketi, backup/restore/rollback |
| 24–25 | Staging real sinovi, production deploy va topshirish |

## Promptlar ro‘yxati

| № | Prompt | Oldingi shart |
|---|---|---|
| 01 | Loyihani tayyorlash va arxitektura qarorlarini qayd qilish | Arxitektura mavjud |
| 02 | Takror ko‘tariladigan lokal muhit va CI | 01 DONE |
| 03 | Kirish, ruxsatlar va responsive dastur karkasi | 02 DONE |
| 04 | Katalog, mijoz va ta’minotchi boshqaruvi | 03 DONE |
| 05 | Operatsiya ID, tranzaksiya, audit va hodisa navbati | 04 DONE |
| 06 | Hisob daftarlari, tannarx va boshlang‘ich qoldiqlar | 05 DONE |
| 07 | Kirim bo‘limi — backenddan oynagacha | 06 DONE |
| 08 | Sotuv bo‘limi — tezkor, mijozli va nasiya | 07 DONE |
| 09 | Qarzdorliklar va taraflar to‘lovlari | 08 DONE |
| 10 | Kassa, xarajat, o‘tkazma va smena | 09 DONE |
| 11 | Offline qurilmalar, qoldiq va kredit ajratmalari | 10 DONE |
| 12 | Server sync API va konfliktlar protokoli | 11 DONE |
| 13 | PWA lokal baza va internetsiz sotuv | 12 DONE |
| 14 | PWA avtomatik sync va uzilish sinovlari | 13 DONE |
| 15 | Ombor qoldiqlari va interaktiv kalkulyator | 14 DONE |
| 16 | Qaytarish, brak, inventarizatsiya va tuzatish | 15 DONE |
| 17 | Savdo tarixi, hisobotlar va eksportlar | 16 DONE |
| 18 | Dashboard, Admin va real vaqt yangilanishlari | 17 DONE |
| 19 | Telegram orqali ko‘rish, kirim, sotuv va to‘lov | 18 DONE |
| 20 | Flutter Android: kirish va online biznes oynalari | 19 DONE |
| 21 | Flutter offline savdo, sync va reliz paketi | 20 DONE |
| 22 | To‘liq tizim testlari, xavfsizlik va yuklama | 21 DONE |
| 23 | Production paketi, backup va tiklash rejasi | 22 DONE |
| 24 | Staging deploy va haqiqiy qurilmalarda qabul sinovi | 23 DONE |
| 25 | Productionga chiqarish va yakuniy topshirish | 24 DONE |

## Prompt 01 — Loyihani tayyorlash va arxitektura qarorlarini qayd qilish

Guruh: Poydevor. Oldingi shart: boshlanish.

```text
Loyiha: D:\Project\CRM.
Avval D:\Project\CRM\SUV_DOKONI_CRM_ARXITEKTURA_V2.md va D:\Project\CRM\QURISH_PROMPTLARI_25.md dagi umumiy qoidalarni o‘qing. QURILISH_HOLATI.md mavjud bo‘lsa uni va tegishli AGENTS.md’ni tekshiring.
Faqat 01-bosqichni bajaring. Bu boshlang‘ich bosqich; funksiyalarni bajarilgan deb taxmin qilmang.

Faqat shu bosqichni bajaring. Mavjud backend/mobile, AGENTS.md, vositalar, env va baza holatini inventarizatsiya qiling. Ilova o‘zgarishidan oldin backend/AGENTS.md’dagi setup talabini bajarib, yangilangan ko‘rsatmalarni qayta o‘qing. PHP, Composer, Node, Flutter, Java va PostgreSQL mosligini tekshiring; tasodifiy major upgrade qilmang. Mavjud ma’lumot va foydalanuvchi fayllarini xavfsiz saqlang; Git yo‘q bo‘lsa maxfiy env/baza/generated output exclude qilinganidan keyin lokal repo yarating. Arxitektura asosiy hujjat: bitta do‘kon/ombor, faqat dona, umumiy qarz, offline savdo va operation_id. Standart reliz yo‘li sifatida desktop/telefon PWA hamda mavjud mobile katalogida Flutter Android, parallel offline savdo uchun ajratma, alohida Kassa bo‘limini qayd qiling. Bu standartlarni foydalanuvchining keyingi ko‘rsatmasi almashtirishi mumkin. QURILISH_HOLATI.md yarating: 25 bosqich, holat, dalil, muhit va qoldiq ish.

Qabul: tiklanadigan manba/ma’lumot holati, ishlaydigan vositalar va 25 bosqich reyestri bor. Eski demo test xatolari baseline sifatida aniq qayd qilingan; ilova tayyor deb da’vo qilinmagan. Haqiqatan yetishmayotgan biznes qarori yoki runtime cheklovi alohida ko‘rsatilgan.

Yakunida QURILISH_HOLATI.md’ga dalillarni yozing: bajarilgan ish, o‘zgargan fayllar, tekshiruv buyruqlari/natijalari va muhit, qolgan cheklov. Majburiy check o‘tmaguncha DONE yozmang. Keyingi promptni avtomatik boshlamang.
```

## Prompt 02 — Takror ko‘tariladigan lokal muhit va CI

Guruh: Poydevor. Oldingi shart: 01 DONE.

```text
Loyiha: D:\Project\CRM.
Avval D:\Project\CRM\SUV_DOKONI_CRM_ARXITEKTURA_V2.md va D:\Project\CRM\QURISH_PROMPTLARI_25.md dagi umumiy qoidalarni o‘qing. QURILISH_HOLATI.md mavjud bo‘lsa uni va tegishli AGENTS.md’ni tekshiring.
Faqat 02-bosqichni bajaring. Avval 01-bosqichning majburiy qabul mezonlari o‘tganini dalildan tekshiring. DONE bo‘lmasa dependency’ni tuzatmasdan oldinga o‘tmang.

Development/test uchun alohida PostgreSQL bazalari, Redis, web/queue/scheduler va frontend build muhitini tayyorlang. Windowsdagi ishlaydigan runtime’dan foydalaning; Docker mavjudligini taxmin qilmang. Dependency lockfile va .env.example’da faqat namuna qiymatlar bo‘lsin. Test bazasini nom/ulanish bo‘yicha himoyalang, real bazada migrate:fresh ishlamasin. CI’da backend test/lint, frontend build, lokal POS testlari va mobile uchun keyinchalik ishlaydigan Flutter tekshiruvlarini tartiblang. Tashqi Telegram/bank va boshqa ta’sirlar testda fake bo‘lsin. DB migration, row locking va concurrency uchun SQLite o‘rniga haqiqiy test PostgreSQL kerak. Boshlang‘ich eski failing demo tekshiruvlarini yashirmasdan yangi arxitektura testlariga almashtirish rejasini qayd qiling.

Qabul: toza test PostgreSQL’da migration, connection va rollback sinovi o‘tadi; build takrorlanadi, CI bosqichlari aniq. Test jarayonida real hisob/baza yoki Telegramga tegilmagan. Lokal ishga tushirish buyruqlari va muhit talablari hujjatlashtirilgan.

Yakunida QURILISH_HOLATI.md’ga dalillarni yozing: bajarilgan ish, o‘zgargan fayllar, tekshiruv buyruqlari/natijalari va muhit, qolgan cheklov. Majburiy check o‘tmaguncha DONE yozmang. Keyingi promptni avtomatik boshlamang.
```

## Prompt 03 — Kirish, ruxsatlar va responsive dastur karkasi

Guruh: Poydevor. Oldingi shart: 02 DONE.

```text
Loyiha: D:\Project\CRM.
Avval D:\Project\CRM\SUV_DOKONI_CRM_ARXITEKTURA_V2.md va D:\Project\CRM\QURISH_PROMPTLARI_25.md dagi umumiy qoidalarni o‘qing. QURILISH_HOLATI.md mavjud bo‘lsa uni va tegishli AGENTS.md’ni tekshiring.
Faqat 03-bosqichni bajaring. Avval 02-bosqichning majburiy qabul mezonlari o‘tganini dalildan tekshiring. DONE bo‘lmasa dependency’ni tuzatmasdan oldinga o‘tmang.

Login/logout, sessiya/token, egasi/admin/sotuvchi/omborchi/moliya rollari va aniq permissionsni yarating. Web/API/eksport/bot uchun bir xil server policy; barcha mavjud hisobga ta’sir qiladigan public yo‘llarni himoyalang. Default parolsiz birinchi owner bootstrap usuli bo‘lsin. Tannarx, nasiya, erkin narx, refund, pul chiqimi, offline va narx boshqaruvi alohida huquqlar. Responsive Livewire layout, mahalliy build CSS/JS, jadval/modal/filtr/validatsiya komponentlari va sakkizta menyuni yarating: Dashboard, Savdo, Sotuv, Ombor, Qarzdorliklar, Hisobotlar, Admin, Kassa va xarajatlar. Savdo tarix, Sotuv yangi operatsiya. Hali qurilmagan sahifalarda demo raqam yoki fake success bo‘lmasin.

Qabul: anonim 401/login, noto‘g‘ri rol 403; to‘g‘ri rolga kerakli sahifa ochiladi. Logout/bloklash/token va desktop/360 px ekran smoke o‘tadi. Tannarx yashirish faqat UI emas, API va policy’da ham bajariladi.

Yakunida QURILISH_HOLATI.md’ga dalillarni yozing: bajarilgan ish, o‘zgargan fayllar, tekshiruv buyruqlari/natijalari va muhit, qolgan cheklov. Majburiy check o‘tmaguncha DONE yozmang. Keyingi promptni avtomatik boshlamang.
```

## Prompt 04 — Katalog, mijoz va ta’minotchi boshqaruvi

Guruh: Biznes asoslari. Oldingi shart: 03 DONE.

```text
Loyiha: D:\Project\CRM.
Avval D:\Project\CRM\SUV_DOKONI_CRM_ARXITEKTURA_V2.md va D:\Project\CRM\QURISH_PROMPTLARI_25.md dagi umumiy qoidalarni o‘qing. QURILISH_HOLATI.md mavjud bo‘lsa uni va tegishli AGENTS.md’ni tekshiring.
Faqat 04-bosqichni bajaring. Avval 03-bosqichning majburiy qabul mezonlari o‘tganini dalildan tekshiring. DONE bo‘lmasa dependency’ni tuzatmasdan oldinga o‘tmang.

Mahsulot/hajm/variant, mijoz, ta’minotchi va price history schema hamda umumiy backend amallarini qurib Livewire sahifaga ulang. Mahsulotga nom yetadi, ID/kod server beradi; hajm integer ml, product+volume unique, dona yagona miqdor birligi. Fanta/ fanta va 0.5/0,5 L/500 ml normalizatsiyasi, concurrent duplicate himoyasi. Mijoz ism/telefon/manzil/do‘kon nomi; telefon yo‘q holat arxitekturaga mos farqlansin. Telefon yoki o‘xshash ism bilan avtomatik taraf merge yo‘q. Offline mijoz uchun barqaror UUID. Kirim/sotuvdan ishlatiladigan mahsulot/hajm/ta’minotchi/mijoz inline modallarini yarating: saqlangach yangi yozuv tanlansin, qoralama qoladi. Variantning tizim narxi, yo‘q narx holati, kam qoldiq va arxiv; narx o‘zgarishi ruxsat/history/versiya bilan.

Qabul: nom yozib yaratish, hajm dropdown/+qo‘shish, taraf qidiruvi va narx tahriri ishlaydi. DB cheklovlari, parallel yaratish, UUID retry va modal qoralama saqlashi tekshirilgan; tarixda ishlatilgan yozuvlar o‘chirilmaydi.

Yakunida QURILISH_HOLATI.md’ga dalillarni yozing: bajarilgan ish, o‘zgargan fayllar, tekshiruv buyruqlari/natijalari va muhit, qolgan cheklov. Majburiy check o‘tmaguncha DONE yozmang. Keyingi promptni avtomatik boshlamang.
```

## Prompt 05 — Operatsiya ID, tranzaksiya, audit va hodisa navbati

Guruh: Hisob poydevori. Oldingi shart: 04 DONE.

```text
Loyiha: D:\Project\CRM.
Avval D:\Project\CRM\SUV_DOKONI_CRM_ARXITEKTURA_V2.md va D:\Project\CRM\QURISH_PROMPTLARI_25.md dagi umumiy qoidalarni o‘qing. QURILISH_HOLATI.md mavjud bo‘lsa uni va tegishli AGENTS.md’ni tekshiring.
Faqat 05-bosqichni bajaring. Avval 04-bosqichning majburiy qabul mezonlari o‘tganini dalildan tekshiring. DONE bo‘lmasa dependency’ni tuzatmasdan oldinga o‘tmang.

Barcha yozuvchi amallar uchun umumiy operation_id, canonical payload fingerprint, actor/device/source, vaqt, expected version va error kontraktini yarating. Bir ID+bir payload eski natijani beradi, shu ID boshqa ma’lumot bilan konflikt. DB unikal cheklov va result yozuvi hisob operatsiyasi bilan bitta transactionda bo‘lsin. Audit, outbox va commitdan keyingi worker oqimini tayyorlang; rollbackdan keyin event yo‘q. Client timeoutni yangi savdoga aylantirmang. Xatolar validation/permission/retryable/needs-reviewga ajratilsin, secret/raw exception API’da chiqmasin. Hujjat raqami max(id)+1 bilan emas, concurrency-safe usulda. Umumiy yozuv vositasi keyingi modullarga ulanadigan bo‘lsin.

Qabul: test PostgreSQL’da 20 parallel bir ID bitta sinov operatsiyasi beradi; javob yo‘qolganda eski natija olinadi; boshqa payload o‘tmaydi. Yarim operation result/audit/outbox yo‘q; worker qayta ishlaganda biznes yozuvi takror yaratilmaydi.

Yakunida QURILISH_HOLATI.md’ga dalillarni yozing: bajarilgan ish, o‘zgargan fayllar, tekshiruv buyruqlari/natijalari va muhit, qolgan cheklov. Majburiy check o‘tmaguncha DONE yozmang. Keyingi promptni avtomatik boshlamang.
```

## Prompt 06 — Hisob daftarlari, tannarx va boshlang‘ich qoldiqlar

Guruh: Hisob poydevori. Oldingi shart: 05 DONE.

```text
Loyiha: D:\Project\CRM.
Avval D:\Project\CRM\SUV_DOKONI_CRM_ARXITEKTURA_V2.md va D:\Project\CRM\QURISH_PROMPTLARI_25.md dagi umumiy qoidalarni o‘qing. QURILISH_HOLATI.md mavjud bo‘lsa uni va tegishli AGENTS.md’ni tekshiring.
Faqat 06-bosqichni bajaring. Avval 05-bosqichning majburiy qabul mezonlari o‘tganini dalildan tekshiring. DONE bo‘lmasa dependency’ni tuzatmasdan oldinga o‘tmang.

inventory_movements/balances, customer_ledger, supplier_ledger, cash_accounts/movements va paymentsni qurib umumiy servislar yarating. Ledger asosiy, cached qoldiq tranzaksiyada yangilanadi. Pul integer so‘m, aniq API serializatsiya va overflow himoya, binary float yo‘q. Ombor dona+jami qiymat, WAC va chiqim tannarxini izchil yaxlitlash; oxirgi dona sotilganda qiymat ham 0. Taraf hisobida musbat qarz/manfiy avans, max(0) orqali credit yo‘qolmasin. Boshlang‘ich ombor qiymati, cash/card/bank va har taraf qarz/avansini alohida idempotent ochilish hujjati va owner UI orqali kiriting. Demo seed production hisobini yaratmasin. Hozir haqiqiy import qilmang.

Qabul: 100×5000+100×6000 = 200 dona/1100000 qiymat/WAC5500; ledger va cached hisoblar teng. Opening retry bitta yozuv. Yaxlitlash, row locks, parallel hisob va rollback PostgreSQL’da o‘tadi; manfiy taraf hisobi avans sifatida ko‘rinadi.

Yakunida QURILISH_HOLATI.md’ga dalillarni yozing: bajarilgan ish, o‘zgargan fayllar, tekshiruv buyruqlari/natijalari va muhit, qolgan cheklov. Majburiy check o‘tmaguncha DONE yozmang. Keyingi promptni avtomatik boshlamang.
```

## Prompt 07 — Kirim bo‘limi — backenddan oynagacha

Guruh: Kundalik ish. Oldingi shart: 06 DONE.

```text
Loyiha: D:\Project\CRM.
Avval D:\Project\CRM\SUV_DOKONI_CRM_ARXITEKTURA_V2.md va D:\Project\CRM\QURISH_PROMPTLARI_25.md dagi umumiy qoidalarni o‘qing. QURILISH_HOLATI.md mavjud bo‘lsa uni va tegishli AGENTS.md’ni tekshiring.
Faqat 07-bosqichni bajaring. Avval 06-bosqichning majburiy qabul mezonlari o‘tganini dalildan tekshiring. DONE bo‘lmasa dependency’ni tuzatmasdan oldinga o‘tmang.

Ta’minotchi va ko‘p qatorli mahsulot/hajm/dona/bir dona narx bilan DRAFT→POSTED kirimni amalga oshiring. Inline katalog/ta’minotchi modallarini shu oynaga ulang. Sotuv narxi kirimda majburiy emas. To‘liq/qisman/to‘lovsiz kirim va pul hisobini tanlash moliya ruxsati bilan; omborchining jim qolishi naqd to‘lov deb olinmasin. Postda purchase/items, stock quantity/value, supplier ledger, haqiqiy payment/cash, audit/outbox atomik. Ta’minotchi majburiy, nakladnoy/izoh ixtiyoriy; ochilish kirimi alohida. operation_id qoralamada barqaror, retry eski natijaga olib boradi.

Qabul: Fanta0.5L150×5000 = 750000 va +150. Supplierga300000 to‘lov bo‘lsa qarz450000/cash−300000; to‘lovsiz cash o‘zgarmaydi. Double-click/parallel kirim va bir qator xatosida rollback testlangan; UI qoralamasi saqlanadi.

Yakunida QURILISH_HOLATI.md’ga dalillarni yozing: bajarilgan ish, o‘zgargan fayllar, tekshiruv buyruqlari/natijalari va muhit, qolgan cheklov. Majburiy check o‘tmaguncha DONE yozmang. Keyingi promptni avtomatik boshlamang.
```

## Prompt 08 — Sotuv bo‘limi — tezkor, mijozli va nasiya

Guruh: Kundalik ish. Oldingi shart: 07 DONE.

```text
Loyiha: D:\Project\CRM.
Avval D:\Project\CRM\SUV_DOKONI_CRM_ARXITEKTURA_V2.md va D:\Project\CRM\QURISH_PROMPTLARI_25.md dagi umumiy qoidalarni o‘qing. QURILISH_HOLATI.md mavjud bo‘lsa uni va tegishli AGENTS.md’ni tekshiring.
Faqat 08-bosqichni bajaring. Avval 07-bosqichning majburiy qabul mezonlari o‘tganini dalildan tekshiring. DONE bo‘lmasa dependency’ni tuzatmasdan oldinga o‘tmang.

Umumiy CreateSale va responsive desktop/telefon Sotuv oynasini qurish. Guest tezkor savdo faqat to‘liq to‘lov; mijozli savdoda inline yangi mijoz, to‘liq/qisman/nasiya. Savat mahsulot+hajm+dona, tizim narxi checkbox/erkin narx; narx yo‘q bo‘lsa tasodifiy default yo‘q. Jami, hozir olingan pul, avans, eski/yangi qarz ko‘rinadi. Bir variant takror qatorlarda bo‘lsa barcha dona jamlanib lock tekshiriladi, turli narxlar saqlanishi mumkin. Sale/items/cost snapshot/stock/customer ledger/cash/audit/outbox atomik. Mijoz daftariga jami qo‘shiladi, haqiqiy pul ayiriladi; avans ikkinchi cash emas. Online eski narx versiyasida qayta tasdiq. POS interfeysini keyingi lokal adapterga mos ajrating.

Qabul: 60×6500 jami390000/cost300000/paid140000/debt250000/gross90000. Mijozsiz DEBT, manfiy yoki nol narx, kasr dona va 100qoldiqdan60+60 o‘tmaydi. 20 bosish/parallel so‘rov bitta chek; xatoda savat qoladi. Elektron hujjat va klaviatura/mobile oqimi ishlaydi.

Yakunida QURILISH_HOLATI.md’ga dalillarni yozing: bajarilgan ish, o‘zgargan fayllar, tekshiruv buyruqlari/natijalari va muhit, qolgan cheklov. Majburiy check o‘tmaguncha DONE yozmang. Keyingi promptni avtomatik boshlamang.
```

## Prompt 09 — Qarzdorliklar va taraflar to‘lovlari

Guruh: Kundalik ish. Oldingi shart: 08 DONE.

```text
Loyiha: D:\Project\CRM.
Avval D:\Project\CRM\SUV_DOKONI_CRM_ARXITEKTURA_V2.md va D:\Project\CRM\QURISH_PROMPTLARI_25.md dagi umumiy qoidalarni o‘qing. QURILISH_HOLATI.md mavjud bo‘lsa uni va tegishli AGENTS.md’ni tekshiring.
Faqat 09-bosqichni bajaring. Avval 08-bosqichning majburiy qabul mezonlari o‘tganini dalildan tekshiring. DONE bo‘lmasa dependency’ni tuzatmasdan oldinga o‘tmang.

Mijoz va ta’minotchi tablari, kartalar, umumiy signed balans va hisob ko‘chirmasini yarating. Savdo/kirim qatorlari, mahsulot+hajm+dona, actor, hodisa vaqti/server vaqti va izohdan hujjatga o‘tish. Kamera uchun vaqtlar sekund aniqligida Asia/Tashkent; ixtiyoriy tovar olib ketilgan vaqt alohida, taxminiy CCTV integratsiyasi yo‘q. Mijoz payment/supplier payment umumiy servislari cash bilan atomik. Keyingi to‘lovni mahsulot/checklarga majburiy allocate qilmang; qarzdan ortiq summa aniq tasdiqli avans. Kredit limiti/to‘lov sanasi sozlamalari bo‘lsin, invoice-level overdue’ni umumiy qarzdan to‘qimang.

Qabul: keyingi100000 payment qarzni kamaytiradi, oldingi savdo/foyda o‘zgarmaydi; supplier to‘lovi stock’ga tegmaydi. Boshlang‘ich/yakuniy ko‘chirma teng, mijoz avansi boshqa mijoz qarzini yashirmaydi. Retry, ruxsat, vaqt filtri va rollback tekshiruvlari o‘tadi.

Yakunida QURILISH_HOLATI.md’ga dalillarni yozing: bajarilgan ish, o‘zgargan fayllar, tekshiruv buyruqlari/natijalari va muhit, qolgan cheklov. Majburiy check o‘tmaguncha DONE yozmang. Keyingi promptni avtomatik boshlamang.
```

## Prompt 10 — Kassa, xarajat, o‘tkazma va smena

Guruh: Kundalik ish. Oldingi shart: 09 DONE.

```text
Loyiha: D:\Project\CRM.
Avval D:\Project\CRM\SUV_DOKONI_CRM_ARXITEKTURA_V2.md va D:\Project\CRM\QURISH_PROMPTLARI_25.md dagi umumiy qoidalarni o‘qing. QURILISH_HOLATI.md mavjud bo‘lsa uni va tegishli AGENTS.md’ni tekshiring.
Faqat 10-bosqichni bajaring. Avval 09-bosqichning majburiy qabul mezonlari o‘tganini dalildan tekshiring. DONE bo‘lmasa dependency’ni tuzatmasdan oldinga o‘tmang.

Cash/card/bank hisoblari va pul harakati sahifalarini yarating. Operatsion expense, owner funding/draw, transfer, to‘lovlar va refundga mos umumiy cash kontrakti. Transfer ikki hisob harakati, yangi savdo/foyda emas; owner draw operating expense emas. Pul yetarliligi transaction/lock ichida. Bitta naqd hisob uchun bitta open session, opening amount, kutilgan/haqiqiy yopilish va sababli farq. Pending/offline device aniqlanmaguncha close provisional yoki muvofiqlashishni kutadi; eski sessionga kech kelgan savdo yashirin yozilmaydi. Farqni yashirin balance overwrite bilan tuzatmang.

Qabul: parallel pul chiqim hisobdan ortiq sarflamaydi, retry bitta expense/transfer. Startingcash500000, supplier−300000, sale+140000, debt+100000, supplier−50000 =390000. Session open uniqueness/closed guard va ruxsatli farq hujjati testlangan.

Yakunida QURILISH_HOLATI.md’ga dalillarni yozing: bajarilgan ish, o‘zgargan fayllar, tekshiruv buyruqlari/natijalari va muhit, qolgan cheklov. Majburiy check o‘tmaguncha DONE yozmang. Keyingi promptni avtomatik boshlamang.
```

## Prompt 11 — Offline qurilmalar, qoldiq va kredit ajratmalari

Guruh: Offline poydevori. Oldingi shart: 10 DONE.

```text
Loyiha: D:\Project\CRM.
Avval D:\Project\CRM\SUV_DOKONI_CRM_ARXITEKTURA_V2.md va D:\Project\CRM\QURISH_PROMPTLARI_25.md dagi umumiy qoidalarni o‘qing. QURILISH_HOLATI.md mavjud bo‘lsa uni va tegishli AGENTS.md’ni tekshiring.
Faqat 11-bosqichni bajaring. Avval 10-bosqichning majburiy qabul mezonlari o‘tganini dalildan tekshiring. DONE bo‘lmasa dependency’ni tuzatmasdan oldinga o‘tmang.

Device registration, imzolangan muddatli permission snapshot va stock allocation berish/sarflash/qaytarish amallarini qurish. Fizik qoldiq va sotish huquqi rezervi alohida: 100 dona PC60/phone30/free10. Online savdo o‘z rezervi yoki erkin qoldiqni sarflasin. Rezerv epoch/version va idempotent iste’mol; expiry/offline/reinstall rezervni avtomatik boshqa devicega bermaydi. Yo‘qolgan device manual reconcile. Qat’iy customer credit limit yoqilsa bo‘sh limitni ham qurilmalarga ajrating; yangi offline mijoz nasiya byudjeti/ruxsati sozlanadi. Admin’da qurilmalar, ajratmalar va oxirgi aloqa. Stock kamaytiradigan keyingi return/brak/count ham rezerv kontraktiga majburiy ulanadi.

Qabul: parallel grant/consume ajratmalar yig‘indisini stockdan oshirmaydi; bir operation_id rezervni ikki sarflamaydi. Credit limit online/offline rezervni hisobga oladi; revoke/expiry cheklovi ko‘rinadi, avvalgi pending savdo yo‘qolmaydi. Readiness/lease, insufficient allocation va yo‘qolgan device ssenariylari testlangan.

Yakunida QURILISH_HOLATI.md’ga dalillarni yozing: bajarilgan ish, o‘zgargan fayllar, tekshiruv buyruqlari/natijalari va muhit, qolgan cheklov. Majburiy check o‘tmaguncha DONE yozmang. Keyingi promptni avtomatik boshlamang.
```

## Prompt 12 — Server sync API va konfliktlar protokoli

Guruh: Offline poydevori. Oldingi shart: 11 DONE.

```text
Loyiha: D:\Project\CRM.
Avval D:\Project\CRM\SUV_DOKONI_CRM_ARXITEKTURA_V2.md va D:\Project\CRM\QURISH_PROMPTLARI_25.md dagi umumiy qoidalarni o‘qing. QURILISH_HOLATI.md mavjud bo‘lsa uni va tegishli AGENTS.md’ni tekshiring.
Faqat 12-bosqichni bajaring. Avval 11-bosqichning majburiy qabul mezonlari o‘tganini dalildan tekshiring. DONE bo‘lmasa dependency’ni tuzatmasdan oldinga o‘tmang.

Versiyalangan device bootstrap, ruxsatli snapshot, batch push, operation status va cursor pull endpointlarini yarating. Har operation alohida natija, bir savdo ichida atomik hisob. UUID yangi mijoz avval server ID’ga ulanadi, keyin savdo; o‘xshash telefon/nomsiz automerge yo‘q. operation_id va canonical payload retry/mappingda o‘zgarmasin. Commit qilingan change feed, entity version/tombstone, pagination va cursor; kech commit past ID’ni yo‘qotadigan max-ID polling yo‘q. Offline narx saqlanadi, final cost server posting tartibida, device time/server received/posted ajratiladi. Late closed session, lease/limit/mapping xatosi NEEDS_REVIEW; yozuv tashlab yuborilmaydi. Admin ko‘rib hal qilish API’si auditli, default originalni o‘chirmaydi.

Qabul: timeout+retry eski natija, mixed batch per-item status, customer dependency, revoked permission, stale price, tombstone va reconnect delta testlangan. Haqiqatan yangi bir xil summali savdolar saqlanadi, bir ID boshqa payload konflikt. PostgreSQL commit-order cursor sinovi o‘tadi.

Yakunida QURILISH_HOLATI.md’ga dalillarni yozing: bajarilgan ish, o‘zgargan fayllar, tekshiruv buyruqlari/natijalari va muhit, qolgan cheklov. Majburiy check o‘tmaguncha DONE yozmang. Keyingi promptni avtomatik boshlamang.
```

## Prompt 13 — PWA lokal baza va internetsiz sotuv

Guruh: Offline PWA. Oldingi shart: 12 DONE.

```text
Loyiha: D:\Project\CRM.
Avval D:\Project\CRM\SUV_DOKONI_CRM_ARXITEKTURA_V2.md va D:\Project\CRM\QURISH_PROMPTLARI_25.md dagi umumiy qoidalarni o‘qing. QURILISH_HOLATI.md mavjud bo‘lsa uni va tegishli AGENTS.md’ni tekshiring.
Faqat 13-bosqichni bajaring. Avval 12-bosqichning majburiy qabul mezonlari o‘tganini dalildan tekshiring. DONE bo‘lmasa dependency’ni tuzatmasdan oldinga o‘tmang.

Kompyuter/telefon uchun installable PWA, Service Worker app shell va IndexedDB lokal schema yarating. Livewire online qoladi, POS internetga bog‘liq bo‘lmagan JS/Alpine qatlamida; server-render cache’ni offline POS deb atamang. Katalog/ruxsatli mijoz/narx/device lease/ajratma, draft, sale/items/payments va navbatni saqlang. Lokal tasdiq, quota/credit kamayishi va outbox enqueue bitta IndexedDB transaction. operation_id qoralamadan, bir necha tab holati atomik. Offline yangi mijoz UUID, to‘liq/qisman/nasiya va cached/system/manual price ishlasin. Lokal pul integer/aniq hisob, tannarx taxminiy belgi. Storage quota/persistence/PIN yoki qayta ochish xavfsizligi, pending eksport va upgrade migratsiyasi. Cache’da boshqa xodimning maxfiy moliyasi qolmasin.

Qabul: oldindan tayyorlangan PWA tarmoqsiz ishga tushib savdo yozadi; reload/restartda chek/navbat saqlanadi. Storage failure’da success yo‘q, ko‘p bosish bitta lokal chek. O‘z quota/ruxsatidan ortiq operatsiya blok; internetda qayta init pending yoki rezervni reset qilmaydi.

Yakunida QURILISH_HOLATI.md’ga dalillarni yozing: bajarilgan ish, o‘zgargan fayllar, tekshiruv buyruqlari/natijalari va muhit, qolgan cheklov. Majburiy check o‘tmaguncha DONE yozmang. Keyingi promptni avtomatik boshlamang.
```

## Prompt 14 — PWA avtomatik sync va uzilish sinovlari

Guruh: Offline PWA. Oldingi shart: 13 DONE.

```text
Loyiha: D:\Project\CRM.
Avval D:\Project\CRM\SUV_DOKONI_CRM_ARXITEKTURA_V2.md va D:\Project\CRM\QURISH_PROMPTLARI_25.md dagi umumiy qoidalarni o‘qing. QURILISH_HOLATI.md mavjud bo‘lsa uni va tegishli AGENTS.md’ni tekshiring.
Faqat 14-bosqichni bajaring. Avval 13-bosqichning majburiy qabul mezonlari o‘tganini dalildan tekshiring. DONE bo‘lmasa dependency’ni tuzatmasdan oldinga o‘tmang.

IndexedDB navbatini API bilan ulang: server health tekshirish, pending push, ACK’ni lokal atomik yozish, cursor pull va qolgan pending overlay. Internet qaytishi, app reopen va qo‘lda sync trigger; mavjud bo‘lsa background sync, yopiq appda barcha platforma uchun darhol yuborish va’dasi yo‘q. Multi-tab/background/foreground worker uchun lokal lock/lease va recovery. Retry original operation_id/payload bilan; timeoutda operation status yoki ayni request. ACK chek/payloadni xavfsiz retention davomida saqlang, faqat queue flagni yakunlang; backup tiklash uchun history kerak. NEEDS_REVIEW, mapping, lease, stale price va late closed day UI’si. Offline bekor qilingan haqiqiy savdo originalga bog‘langan tuzatish, navbatdan delete emas.

Qabul: brauzer E2E’da internetni uzish/ulash, restart, javobni commitdan keyin yo‘qotish, ikki tab va ikki device bilan bitta operatsiya bir marta serverga yoziladi. Qolgan pending/ACK/history yo‘qolmaydi; cursor va rezerv teng. Pul/qarz/stock server ledgerga moslashadi, qo‘lda reload shart emas.

Yakunida QURILISH_HOLATI.md’ga dalillarni yozing: bajarilgan ish, o‘zgargan fayllar, tekshiruv buyruqlari/natijalari va muhit, qolgan cheklov. Majburiy check o‘tmaguncha DONE yozmang. Keyingi promptni avtomatik boshlamang.
```

## Prompt 15 — Ombor qoldiqlari va interaktiv kalkulyator

Guruh: Tahlil va nazorat. Oldingi shart: 14 DONE.

```text
Loyiha: D:\Project\CRM.
Avval D:\Project\CRM\SUV_DOKONI_CRM_ARXITEKTURA_V2.md va D:\Project\CRM\QURISH_PROMPTLARI_25.md dagi umumiy qoidalarni o‘qing. QURILISH_HOLATI.md mavjud bo‘lsa uni va tegishli AGENTS.md’ni tekshiring.
Faqat 15-bosqichni bajaring. Avval 14-bosqichning majburiy qabul mezonlari o‘tganini dalildan tekshiring. DONE bo‘lmasa dependency’ni tuzatmasdan oldinga o‘tmang.

Ombor ro‘yxati, variant kartasi va source hujjatga bog‘langan harakat tarixini yarating. Nom/hajm, eng kam/ko‘p qoldiq, threshold, zero, narx yo‘q, arxiv va sekin sotiladigan filtrlar; server qidiruvi paginationdan oldin. Tannarx/qiymat rol bo‘yicha. Kalkulyator mahsulotlar va litr checkboxlari, all/clear, mavjud dona, jami tannarx, tizim sotuv qiymati va kutilayotgan yalpi foydani chiqarsin. Narxsiz variantlar nol narxga tenglashtirilmasin. Taxminiy narx faqat simulation, katalogga saqlash alohida permission. Serverdagi qoldiq, sotish uchun erkin/ajratilgan miqdor va offline eskirgan snapshot farqlansin. Kam qoldiq dashboard/notification event uchun qayd.

Qabul: Fanta barcha hajm vs 1L vs ikki hajm summalari haqiqiy rowsdan; narrow filterdagi yig‘indi butun bazaning filtrlangan natijasi, faqat bitta sahifa emas. Potensial foyda haqiqiy foyda yoki cash deb nomlanmaydi. Cache/sync va permission browser tekshiruvlari o‘tadi.

Yakunida QURILISH_HOLATI.md’ga dalillarni yozing: bajarilgan ish, o‘zgargan fayllar, tekshiruv buyruqlari/natijalari va muhit, qolgan cheklov. Majburiy check o‘tmaguncha DONE yozmang. Keyingi promptni avtomatik boshlamang.
```

## Prompt 16 — Qaytarish, brak, inventarizatsiya va tuzatish

Guruh: Tahlil va nazorat. Oldingi shart: 15 DONE.

```text
Loyiha: D:\Project\CRM.
Avval D:\Project\CRM\SUV_DOKONI_CRM_ARXITEKTURA_V2.md va D:\Project\CRM\QURISH_PROMPTLARI_25.md dagi umumiy qoidalarni o‘qing. QURILISH_HOLATI.md mavjud bo‘lsa uni va tegishli AGENTS.md’ni tekshiring.
Faqat 16-bosqichni bajaring. Avval 15-bosqichning majburiy qabul mezonlari o‘tganini dalildan tekshiring. DONE bo‘lmasa dependency’ni tuzatmasdan oldinga o‘tmang.

Original savdo/kirimga bog‘langan qisman/to‘liq return, refund va tarixli correction yozing. Sotilgandan ko‘p qaytarish yo‘q; sale return original sotuv narxi/cost snapshotni, supplier return commercial credit va joriy WAC chiqimini/farqni ishlatadi. Yaroqsiz qaytgan tovar sotiladigan qoldiqqa qo‘shilmasin. Mijoz signed balance/refund va cash qayta hisob; pul avtomatik delete emas. Brak tannarx yo‘qotishi, cash expense emas. Inventarizatsiya prepare→device sync/rezerv qaytarish va freeze ACK→sanash→tasdiq; uzilgan device blokni olmagan bo‘lsa yakuniy tasdiq kutadi. Sababli stock/value farq va audit, posted hujjat o‘chirilmasin.

Qabul: qisman returnlar sum/cost yaxlitlashni oxirida yopadi; rezervga qarshi supplier return/brak stockni yashirin sarflamaydi. Refund cash/qarzni ikki kamaytirmaydi. Parallel count/sale, duplicate return, insufficient refund, partial rollback va eski hujjat o‘zgarmasligi testlangan.

Yakunida QURILISH_HOLATI.md’ga dalillarni yozing: bajarilgan ish, o‘zgargan fayllar, tekshiruv buyruqlari/natijalari va muhit, qolgan cheklov. Majburiy check o‘tmaguncha DONE yozmang. Keyingi promptni avtomatik boshlamang.
```

## Prompt 17 — Savdo tarixi, hisobotlar va eksportlar

Guruh: Tahlil va nazorat. Oldingi shart: 16 DONE.

```text
Loyiha: D:\Project\CRM.
Avval D:\Project\CRM\SUV_DOKONI_CRM_ARXITEKTURA_V2.md va D:\Project\CRM\QURISH_PROMPTLARI_25.md dagi umumiy qoidalarni o‘qing. QURILISH_HOLATI.md mavjud bo‘lsa uni va tegishli AGENTS.md’ni tekshiring.
Faqat 17-bosqichni bajaring. Avval 16-bosqichning majburiy qabul mezonlari o‘tganini dalildan tekshiring. DONE bo‘lmasa dependency’ni tuzatmasdan oldinga o‘tmang.

Savdo tarixidagi bugun/kecha/hafta/oy/istalgan sana-oraliq/yil+oy va customer/staff/product/volume/source/payment/status filtrlarini qurish. UTC saqlash va Asia/Tashkent kun chegaralari. Dastlabki paid/debt snapshot bilan keyingi umumiy paymentni aralashtirmang; invoice paid statusni allocation yo‘qligida to‘qimang. Savdo, yalpi/operatsion natija, kirim, stock/value, taraf statement, cash/expense/session/return/staff/sync hisobotlarini umumiy query kontrakti bilan bering. Davr bosh/yakun balans ledgerdan, hozirgi snapshot tarixiy balans emas. Excel/PDF queue eksport ruxsatli yopiq download, snapshot davr/filtr/vaqt/to‘liqlik; spreadsheet formulasi kiritilishi xavfiga qarshi escaping.

Qabul: sentabr sale va oktabr debt payment ikki davrda; naqd/karta/bank, qisman/full debt summalar double-count emas. Sahifa/eksport bir filtr uchun bir xil jami, ko‘p sahifali data yo‘qolmaydi. PDF/Excel ochib qiymat/ko‘rinish tekshiriladi; unauthorized export rad.

Yakunida QURILISH_HOLATI.md’ga dalillarni yozing: bajarilgan ish, o‘zgargan fayllar, tekshiruv buyruqlari/natijalari va muhit, qolgan cheklov. Majburiy check o‘tmaguncha DONE yozmang. Keyingi promptni avtomatik boshlamang.
```

## Prompt 18 — Dashboard, Admin va real vaqt yangilanishlari

Guruh: Boshqaruv. Oldingi shart: 17 DONE.

```text
Loyiha: D:\Project\CRM.
Avval D:\Project\CRM\SUV_DOKONI_CRM_ARXITEKTURA_V2.md va D:\Project\CRM\QURISH_PROMPTLARI_25.md dagi umumiy qoidalarni o‘qing. QURILISH_HOLATI.md mavjud bo‘lsa uni va tegishli AGENTS.md’ni tekshiring.
Faqat 18-bosqichni bajaring. Avval 17-bosqichning majburiy qabul mezonlari o‘tganini dalildan tekshiring. DONE bo‘lmasa dependency’ni tuzatmasdan oldinga o‘tmang.

Rolga mos dashboardni real queries bilan to‘ldiring: savdo/pul/yangi nasiya/gross/expense, cash/card/bank, taraf qarz/avanslari alohida, stock/cost/potential, warning va oxirgi amallar. Davr flow bilan current/as-of balance farqi ko‘rinadi, offline device tufayli to‘liqlik belgisi bor. Admin’da users/permissions, settings, device/offline allocations, audit, NEEDS_REVIEW va sababli resolution, outbox/worker/backup holatiga havola. Reverb/Echo yopiq kanallar va commitdan keyin event/outbox tarqatish. Flutterga mos event invalidation API, reconnectda cursor catch-up. Ochiq savat/modal/filter yangilanishda yo‘qolmasin; cached moliya noto‘g‘ri rolga tarqalmasin.

Qabul: ikki browserda kirim/sale/payment qoldiq va kartalarni yangilaydi, rollback event yubormaydi. Event duplicate/out-of-order va socket reconnect hisobni buzmaydi. Private channel permission, draft retention va NEEDS_REVIEWni hal qilish auditi browser/PG bilan tekshirilgan.

Yakunida QURILISH_HOLATI.md’ga dalillarni yozing: bajarilgan ish, o‘zgargan fayllar, tekshiruv buyruqlari/natijalari va muhit, qolgan cheklov. Majburiy check o‘tmaguncha DONE yozmang. Keyingi promptni avtomatik boshlamang.
```

## Prompt 19 — Telegram orqali ko‘rish, kirim, sotuv va to‘lov

Guruh: Telegram. Oldingi shart: 18 DONE.

```text
Loyiha: D:\Project\CRM.
Avval D:\Project\CRM\SUV_DOKONI_CRM_ARXITEKTURA_V2.md va D:\Project\CRM\QURISH_PROMPTLARI_25.md dagi umumiy qoidalarni o‘qing. QURILISH_HOLATI.md mavjud bo‘lsa uni va tegishli AGENTS.md’ni tekshiring.
Faqat 19-bosqichni bajaring. Avval 18-bosqichning majburiy qabul mezonlari o‘tganini dalildan tekshiring. DONE bo‘lmasa dependency’ni tuzatmasdan oldinga o‘tmang.

Webhook secret header, Telegram ID’ni owner tomonidan userga bog‘lash, bot_drafts va update_id dedup bilan botni yozing. Tugmalar/pagination: dashboard, qoldiq, mijoz/supplier qarzi, kassa, sana bo‘yicha report/eksport. Kirim va ko‘p qatorli savdo bosqichlari; inline mahsulot/hajm/taraf yaratish, tizim/manual price, full/partial/debt, to‘lov va yakuniy preview/tasdiq. Backendning ayni amallarini chaqiring, yangi moliyaviy formulalar yozmang. Callback actor/draft/state/operation_id bilan; bir tasdiq turli update’da bitta hujjat. Outboxdan ruxsatli recipientga notification, retry va HTML escaping, unknown delivery biznes hujjatini ko‘paytirmasin. Bot offline ishlamaydi, PWA havolasi bor.

Qabul: automated fake API testida stranger/setupga ruxsat yo‘q, barcha kerakli oqimlar va 20 tasdiq bitta operation. Token/log maxfiy, hisobot ledgerdan. Real bot sinovi alohida test chat bilan staging bosqichida, bu bosqichda oddiy mijoz/xodimlarga xabar yubormang.

Yakunida QURILISH_HOLATI.md’ga dalillarni yozing: bajarilgan ish, o‘zgargan fayllar, tekshiruv buyruqlari/natijalari va muhit, qolgan cheklov. Majburiy check o‘tmaguncha DONE yozmang. Keyingi promptni avtomatik boshlamang.
```

## Prompt 20 — Flutter Android: kirish va online biznes oynalari

Guruh: Mobil ilova. Oldingi shart: 19 DONE.

```text
Loyiha: D:\Project\CRM.
Avval D:\Project\CRM\SUV_DOKONI_CRM_ARXITEKTURA_V2.md va D:\Project\CRM\QURISH_PROMPTLARI_25.md dagi umumiy qoidalarni o‘qing. QURILISH_HOLATI.md mavjud bo‘lsa uni va tegishli AGENTS.md’ni tekshiring.
Faqat 20-bosqichni bajaring. Avval 19-bosqichning majburiy qabul mezonlari o‘tganini dalildan tekshiring. DONE bo‘lmasa dependency’ni tuzatmasdan oldinga o‘tmang.

Mavjud mobile katalogini shu API va arxitekturaga mos qayta ishlang; yangi parallel loyiha yaratmang. Environment orqali API URL, secure token/session, rolga mos navigatsiya, aniq pul DTO va typed errorlar. Demo product/fake offline success’ni olib tashlang. Dashboard, katalog/stock, kalkulyator, sale history, qarz kartasi, report ko‘rish; ruxsatli kirim/to‘lov va Sotuv uchun mahsulot/hajm/mijoz/supplier inline qo‘shish. Quick/customer/full/partial/debt, system/manual price, chek va barqaror operation_id. Backend formulalariga tayaning, UI pullarini float bilan hisoblamang. Adminning murakkab amallari xavfsiz web havolasi bo‘lishi mumkin. Event/cursor uchun repository adapter tayyorlang.

Qabul: flutter analyze/test o‘tadi, eski counter testi haqiqiy ekran/oqim smoke bilan almashtirilgan. Test API bilan login→kirim→sale→debt/payment→stock bir xil kontraktda, ruxsatsiz ma’lumot/token logga chiqmaydi. http://127.0.0.1 hardcode yo‘q; Android development build ishlaydi, real qurilma sinovi 24-bosqichda.

Yakunida QURILISH_HOLATI.md’ga dalillarni yozing: bajarilgan ish, o‘zgargan fayllar, tekshiruv buyruqlari/natijalari va muhit, qolgan cheklov. Majburiy check o‘tmaguncha DONE yozmang. Keyingi promptni avtomatik boshlamang.
```

## Prompt 21 — Flutter offline savdo, sync va reliz paketi

Guruh: Mobil ilova. Oldingi shart: 20 DONE.

```text
Loyiha: D:\Project\CRM.
Avval D:\Project\CRM\SUV_DOKONI_CRM_ARXITEKTURA_V2.md va D:\Project\CRM\QURISH_PROMPTLARI_25.md dagi umumiy qoidalarni o‘qing. QURILISH_HOLATI.md mavjud bo‘lsa uni va tegishli AGENTS.md’ni tekshiring.
Faqat 21-bosqichni bajaring. Avval 20-bosqichning majburiy qabul mezonlari o‘tganini dalildan tekshiring. DONE bo‘lmasa dependency’ni tuzatmasdan oldinga o‘tmang.

SQLite lokal katalog/mijoz/ruxsat/ajratma/draft/sale/payment/navbat modeli, atomik local confirm, retry/status/push/pull va NEEDS_REVIEWni joriy qiling. PWA bilan ayni protocol/operation_id/price/credit/time qoidalari. Process kill/restart, logout/user switch, lease expiry va storage failure’da pending saqlansin, boshqa userga ochilmasin. Offline customer UUID parent mapping, receipt temp→server raqam va ACK payload retention; background cheklovlarda reopen sync. Lokal DB migration’da navbat yo‘qolmasin. Offline oldindan login/bootstrapdan keyin ishlaydi. Android INTERNET/HTTPS, release environment va haqiqiy release signingni sozlang; keystore/parol Git va javobga kirmasin. APK/AAB build va xavfsiz tarqatish yo‘lini tayyorlang.

Qabul: analyze/test va signed release build o‘tadi; SQLite restart/migration/duplicate-worker testlari bitta savdo beradi. Shared contract fixturelar PWA/backend/Flutterda bir xil natija. Debug key ishlatilmagan; signing credential yo‘q bo‘lsa unsigned/dev artifactni release deb atamang va zarur signing holatini aniq qayd qiling.

Yakunida QURILISH_HOLATI.md’ga dalillarni yozing: bajarilgan ish, o‘zgargan fayllar, tekshiruv buyruqlari/natijalari va muhit, qolgan cheklov. Majburiy check o‘tmaguncha DONE yozmang. Keyingi promptni avtomatik boshlamang.
```

## Prompt 22 — To‘liq tizim testlari, xavfsizlik va yuklama

Guruh: Release sifati. Oldingi shart: 21 DONE.

```text
Loyiha: D:\Project\CRM.
Avval D:\Project\CRM\SUV_DOKONI_CRM_ARXITEKTURA_V2.md va D:\Project\CRM\QURISH_PROMPTLARI_25.md dagi umumiy qoidalarni o‘qing. QURILISH_HOLATI.md mavjud bo‘lsa uni va tegishli AGENTS.md’ni tekshiring.
Faqat 22-bosqichni bajaring. Avval 21-bosqichning majburiy qabul mezonlari o‘tganini dalildan tekshiring. DONE bo‘lmasa dependency’ni tuzatmasdan oldinga o‘tmang.

Barcha oldingi acceptance gate’larni birlashtirib release regressionni bajaring: test PostgreSQL’da parallel sale/payment/allocation/return, 100qoldiqdan60+60, 20 duplicate, payload conflict, crash/rollback. Browser E2E’da offline/reconnect/2tab/2device/socket/drop response; Flutter persistence/protocol testlari. Arxitektura 14.3 misolini to‘liq kirimdan cash390000 yakunigacha, debt/stock/gross alohida tasdiqlang. Auth/API/token/private channel/export/file/webhook/offline cache audit, rate limit va dependency audit. Hisobot katta datasetda paginationdan oldin filtr, aggregate va indexlar; real o‘lchangan latency/queue/sync natijasini yozing. Demo fallback, hardcoded secrets, dev CDN va jim exceptionlarni qidiring. Faqat yangi failure yoki concern bo‘lsa qayta kengaytiring.

Qabul: majburiy avtomatlashtirilgan tekshiruvlar passed, P0/P1 hisob/data-loss/security muammo yo‘q. Xatoni tuzatib tegishli regressionni qayta bajaring, testni skip qilib PASS qilmang. Qo‘llanmagan real Android/server/bot tekshiruvlari hali 24 uchun unverified deb turadi.

Yakunida QURILISH_HOLATI.md’ga dalillarni yozing: bajarilgan ish, o‘zgargan fayllar, tekshiruv buyruqlari/natijalari va muhit, qolgan cheklov. Majburiy check o‘tmaguncha DONE yozmang. Keyingi promptni avtomatik boshlamang.
```

## Prompt 23 — Production paketi, backup va tiklash rejasi

Guruh: Ishga chiqarish. Oldingi shart: 22 DONE.

```text
Loyiha: D:\Project\CRM.
Avval D:\Project\CRM\SUV_DOKONI_CRM_ARXITEKTURA_V2.md va D:\Project\CRM\QURISH_PROMPTLARI_25.md dagi umumiy qoidalarni o‘qing. QURILISH_HOLATI.md mavjud bo‘lsa uni va tegishli AGENTS.md’ni tekshiring.
Faqat 23-bosqichni bajaring. Avval 22-bosqichning majburiy qabul mezonlari o‘tganini dalildan tekshiring. DONE bo‘lmasa dependency’ni tuzatmasdan oldinga o‘tmang.

Kodni deployga tayyorlang: reproducible build/release artifact, migration reja, web/queue/Reverb/scheduler jarayonlari, HTTPS/private uploads, env template, readiness DB/queue va liveness, structured secretsiz logs. Provider nomini taxmin qilmang; konkret deployment paketini tayyorlab mavjud user infrastructure’ga moslang, provider/credential haqiqatan yo‘q bo‘lsa zarur ma’lumotni shundan keyin aniqlang. Backup DB+files, shifrlash/saqlash/RPO/RTO va yangi isolated bazaga real restore drill. Offline ACK history va rezervlarga ta’sirni qo‘shing: eski backupdan qaytganda recovery epoch/ACK watermark orqali oddiy syncni vaqtincha to‘xtatib, saqlangan client operationlarni reconciliation bilan tiklash. Rollback pending formatlariga mos, data’ni drop yoki keyni rotate qilish emas. Release/runbook/training qo‘llanma yozing.

Qabul: release build va deployment konfiguratsiyasi tayyor; restore boshqa DB’da bajarilib ledger/rezervlar tekshirilgan, client pending/ACK recovery dalili bor. Migration va app rollback chegaralari aniq; staging env prodga ulanmaydi. Hozir productionga deploy yoki haqiqiy qoldiq import qilmang.

Yakunida QURILISH_HOLATI.md’ga dalillarni yozing: bajarilgan ish, o‘zgargan fayllar, tekshiruv buyruqlari/natijalari va muhit, qolgan cheklov. Majburiy check o‘tmaguncha DONE yozmang. Keyingi promptni avtomatik boshlamang.
```

## Prompt 24 — Staging deploy va haqiqiy qurilmalarda qabul sinovi

Guruh: Ishga chiqarish. Oldingi shart: 23 DONE.

```text
Loyiha: D:\Project\CRM.
Avval D:\Project\CRM\SUV_DOKONI_CRM_ARXITEKTURA_V2.md va D:\Project\CRM\QURISH_PROMPTLARI_25.md dagi umumiy qoidalarni o‘qing. QURILISH_HOLATI.md mavjud bo‘lsa uni va tegishli AGENTS.md’ni tekshiring.
Faqat 24-bosqichni bajaring. Avval 23-bosqichning majburiy qabul mezonlari o‘tganini dalildan tekshiring. DONE bo‘lmasa dependency’ni tuzatmasdan oldinga o‘tmang.

23-bosqichdagi konkret provider/paket bilan productiondan ajratilgan stagingga deploy qiling. Exact release/build ID, migration, HTTPS, worker/Reverb/scheduler/readinessni serverda tasdiqlang. Bitta PC PWA va haqiqiy Android ilovada login/kirim/sale/partial debt/payment/stock/calc/report/bot amallarini test data bilan bajaring. Aloqani uzib ikkala device’da alohida ajratma bilan offline savdo, restart, reconnect va double-confirm; serverdagi yagona operation va balanslarni tekshiring. Signed APK update lokal pendingni saqlashini tekshiring. Test Telegram bot/chatga user belgilagan receiver bilan webhook/read/write/notification tekshiruvi; production xaridorlariga test xabar yo‘q. Backup/restart/queue failure va rollback mashqi, actual durations, staff qo‘llanmasi va release-readiness GO/NO-GO hisobotini tayyorlang.

Qabul: ayni server va haqiqiy qurilmadagi dalillar bor; tests/builddan tashqari real integrationlar ishlagan. Majburiy check bajarilmasa yoki xavf ochiq bo‘lsa NO-GO, bosqich DONE emas. GO’da release versiyasi, migrate/backup/rollback, qolgan nonblocking cheklovlar va production qabul ro‘yxati aniq.

Yakunida QURILISH_HOLATI.md’ga dalillarni yozing: bajarilgan ish, o‘zgargan fayllar, tekshiruv buyruqlari/natijalari va muhit, qolgan cheklov. Majburiy check o‘tmaguncha DONE yozmang. Keyingi promptni avtomatik boshlamang.
```

## Prompt 25 — Productionga chiqarish va yakuniy topshirish

Guruh: Ishga chiqarish. Oldingi shart: 24 DONE.

```text
Loyiha: D:\Project\CRM.
Avval D:\Project\CRM\SUV_DOKONI_CRM_ARXITEKTURA_V2.md va D:\Project\CRM\QURISH_PROMPTLARI_25.md dagi umumiy qoidalarni o‘qing. QURILISH_HOLATI.md mavjud bo‘lsa uni va tegishli AGENTS.md’ni tekshiring.
Faqat 25-bosqichni bajaring. Avval 24-bosqichning majburiy qabul mezonlari o‘tganini dalildan tekshiring. DONE bo‘lmasa dependency’ni tuzatmasdan oldinga o‘tmang.

Bu prompt user tomonidan alohida yuborilganda, 24-bosqich GO va konkret production environment mavjud bo‘lsa, tasdiqlangan release’ni productionga deploy qilishni bajaring. Kodning aynan tekshirilgan revision/artifactini ishlating. Avval backup va pending/offline-format mosligini tekshiring; xavfsiz migrate/release, jarayonlar, HTTPS va exact deployment natijasini kutib tekshiring. Owner bergan haqiqiy opening data’ni dry-run/mapping/duplicate/reconciliationdan so‘ng idempotent import qiling; summalar/default parollar/demo qoldiqni to‘qimang. Tirik tizimda login/permission/read/report/realtime/bot health smoke; haqiqiy hisobga soxta savdo yozmang. Xodimlar ishini ishga tushirish, kassa/sync holati, alert/backup va bounded restart/recovery tekshiruvi. Xato bo‘lsa ma’lumot yo‘qotmaydigan rollback va aniq sabab. Production URL, release, signed app, qo‘llanma va tekshiruv dalillarini topshiring.

Qabul: actual production deployment muvaffaqiyati, domen/HTTPS, DB/migration, worker/realtime/scheduler, backups, qoldiq/qarz/cash reconciliation va qurilmalar sync’i tasdiqlangan. Barcha 25 bosqich dalilli DONE; lokal/staging PASSni production PASS deb ko‘rsatish yo‘q. Tashqi kirish/credential yoki check yetishmasa konkret unfinished holatni saqlang, «yakunlandi» demang.

Yakunida QURILISH_HOLATI.md’ga dalillarni yozing: bajarilgan ish, o‘zgargan fayllar, tekshiruv buyruqlari/natijalari va muhit, qolgan cheklov. Majburiy check o‘tmaguncha DONE yozmang. Keyingi promptni avtomatik boshlamang.
```

## Production yakunlanganini qachon aytish mumkin?

25-bosqichda aynan chiqarilgan versiya va actual production server tasdiqlangan bo‘lishi kerak. Lokal test yoki staging URLning o‘zi yetarli emas. Do‘konning haqiqiy boshlang‘ich hisoblari, PC/telefon savdo va sync, ruxsatlar, pul/qarz/ombor tengligi, Telegram, hisobot, HTTPS, navbat/realtime/scheduler, backup va tiklash tartibi tekshiriladi.

Testda «o‘tdi», runtime’da «tekshirilmadi» bo‘lgan majburiy talab yakunlangan hisoblanmaydi. Qurilmaning pending/ACK yozuvlarini retention/tiklash yo‘li va foydalanish qo‘llanmasi topshiriladi. Partiya/FEFO, iOS va boshqa keyingi kengaytirishlar asosiy reliz bilan chalkashtirilmaydi.

