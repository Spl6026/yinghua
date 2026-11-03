# 階段六實作與驗證紀錄

更新：2026-10-24。分支：`phase/06-favorites-basic-itinerary`。**已完成本文件所列的本機功能與瀏覽器驗收。** 10-23 曾因工具啟動失敗保留畫面待驗；恢復後以 Brave 實際操作補驗，結果見下方「瀏覽器驗收」。合併前仍須完成歷史備份及最終內容核對；正式部署與其他瀏覽器不在本次通過範圍。

基準為原始 Excel、U-05／U-08／U-12 及 [固定計畫與 6-T01–15](PHASE-6-PLAN.md#8-實作前固定的驗收清單)。S／D／U／E 來源及檔案用途見 [會員指南](../modules/member-resources.md)。階段七未開始；完整多份旅程、名稱／日期／時間／備註編輯、排序與推薦套用仍在階段十一。

## 已建立範圍

- 四個本人收藏 API、三個目前行程 API；合計 68 個操作。會員身分與 CSRF 沿既有管線，管理員 session 單獨不能操作會員資料。
- 015／016 新建 favorites、itineraries、itinerary_items。主／測試庫均為 31 表／297 欄／17 份 migration，原 migration checksum 不改。
- 共用景點愛心及加入入口、我的收藏頁、真實 planner 列表／加入／移除／分頁。私人資料只向本人 API 取得，沒有存進 localStorage 或公開 HTML。
- 保留原型卡片、planner 雙欄／站點及原始 style.css。名稱日期保留位置且唯讀；空資料不顯示原型三站或假日期，保存按鈕位置改為實際重讀操作。這些是本階段功能界線下的明列差異。

## 本輪實際結果

| 驗證 | 結果／範圍 |
| --- | --- |
| 收藏 HTTP／MariaDB／契約 | 69 項檢查、29 個回應契約；本人隔離、重送／並行、游標、停用／登出、草稿與發布未就緒均已測 |
| 目前行程 HTTP／MariaDB／契約 | 100 項檢查、55 個回應契約；首次父子交易、空行程保留、版本衝突、多輪並行及新增／移除競爭、故障 rollback、快照及管理員 cookie 拒絕 |
| 資料層／輸入 | 持久層 25 項全回滾；PHPUnit 共 69 項／270 斷言通過 |
| 前端行為 | 最後修正後 Node 共 129 項通過；包含狀態競態、換帳號、重複掛載待辦、載入中取消、保存成功但重讀失敗、美食原型與登入／註冊切換；DOM 替身不驗排版 |
| 空庫／保留資料升級 | 新 schema 與既有會員／景點升級均 31 表／17 份，重跑安全；公開內容升級另驗原會員／景點／店家保留、FK／唯一約束與未新增結束時間／場地；自有 schema 已刪除 |
| 會員資料容器重建 | 17 項檢查、7 個回應契約；只重建 app-test／web-test，session、收藏、行程及版本保留，重建後仍可移除；沒有重啟 DB 或刪 volume |
| 五輪公開版本切換 | 60 項檢查、22 個回應契約；每輪未發布時私人列表為 unknown，發布後讀到新公開名稱、行程仍保留最初名稱快照；取消／重新收藏成功，沒有增加重試掩蓋 503 |
| 實際查詢計畫 | MariaDB 12.3.3，以 160 筆自有資料驗 EXPLAIN／ANALYZE 後 rollback；收藏列表／游標用 favorite_listing、批次狀態用 PRIMARY、目前行程用 member_current、站點用 itinerary_order，未加額外索引。Performance Schema 關閉，未為此重啟共享 DB |
| 既有功能契約回歸 | 21 組完成、790 個回應／260 個成功輸入、54 個既有操作，契約差異 0；沒有呼叫 Google。測試輸出觀察數不是新增端點數 |
| 階段五補充 | 消息 40 項／12 回應契約、優惠 40 項／8 回應契約、首頁 21 項，公開內容安全及示範初始化補充均完成；公開內容資料層 11 項與時鐘情境 20 項全 rollback，空庫示範初始化 10 項通過 |
| 品質／建置 | PHPStan level 10／PHPat 通過、221 檔格式 dry-run 零修正；應用及工具 Composer strict validate／platform／audit 通過。Rector dry-run 37 檔建議已檢閱，未套用（主要為 new 呼叫括號、nullsafe、array_last 等語法建議，不作業務正確性通過宣告）。主／測試 nginx -t 通過 |
| 前端完整性 | 建置 65 個固定頁，main／ID／label／資產／模組檢查通過；77 個本機 HTTP 檢查零差異，四個沒有合法 ID 的 legacy 詳情預期 404 |
| 原始交付／工程文件 | 原始提交的 69 檔逐 byte 相同，frontend style.css 與原型相同、.env 仍被忽略，851 個本機文件連結存在；main／origin/main 未改動，未推送 |
| 真實瀏覽器 | Brave 桌面／手機、鍵盤、登入意圖、收藏／行程分頁、移除／空值／下架／版本衝突與換帳號返回已實際操作；精確範圍與差異見下節 |

## 固定清單覆蓋與待驗

| ID | 已驗證與保留部分 |
| --- | --- |
| 6-T01 | API／DB 空讀不建表、首次父子保存、最後一站移除保留版本已驗 |
| 6-T02 | HTTP 收藏／重送／並行／第二 client 載回與 JS 同項狀態已驗；瀏覽器列表／美食分類、收藏 20＋3 分頁、跨頁詳情同步及取消已驗 |
| 6-T03 | returnTo 白名單、真實登入返回、query 不自動寫入、取消／刷新及登入與註冊互切保留意圖已驗；註冊提交沿用既有 HTTP 測試，這次沒有操作驗證碼或另建 UI 帳號 |
| 6-T04 | A／B 所有權、owner 欄位拒絕、游標、admin cookie、停用／登出 401 已驗 |
| 6-T05 | CSRF 拒絕、JSON／textContent 安全輸出、no-store／noindex 與不發布私人資料已審查；瀏覽器將含 b 標籤名稱顯示為純文字，無新增 b 節點 |
| 6-T06 | 目前行程不同站並行、相同站重送、舊版本、DB 故障父子回滾已驗 |
| 6-T07 | 草稿／未到排程／刪除／不存在拒絕、舊快照與草稿不外洩、私人引用不阻擋正常軟刪已驗 |
| 6-T08 | 發布未就緒回 unknown、本人移除與復原、私人保存不觸發公開 generation，以及五輪公開版本切換已驗；間歇事件保留如下 |
| 6-T09 | 列表／收藏／詳情／博物館／首頁旅程入口及電影開行程已實測，UUID／legacy resolver 有 HTTP／JS 覆蓋；首頁目前沒有景點愛心卡片，不捏造該 UI。route query 不套用，原型示意三站未存入本人資料 |
| 6-T10 | JS 載入／保存互斥、401／422／503／未知網路結果、舊回應、保存後讀取失敗已驗；瀏覽器實測 409 要求重讀及清空過期列表、移除後焦點、換帳號後上一頁隔離。沒有據此聲稱觸發了 persisted=true 的 bfcache 或實際斷網 |
| 6-T11 | 收藏 20＋3、行程 50＋3／重讀 50、空值／下架、移除焦點與鍵盤已驗；實際 CSS 390×844／1440×900 及 110% 縮放無水平溢出 |
| 6-T12 | 空庫／升級／重跑、資料層約束、隔離容器重建與 EXPLAIN 已驗 |
| 6-T13 | 既有 21 組與階段五補充、七個新操作契約已驗；這次實際操作受影響的登入／註冊切換、景點／美食／博物館／電影及首頁入口，其他既有畫面沿用階段一至五複驗，不冒稱全部重跑 |
| 6-T14 | Docker PHP 品質、前端行為、65 頁建置與完整性已有上述結果 |
| 6-T15 | fixture 僅 yinghua_test／8081；自有收藏／行程／53 景點／測試電影及場次清理，兩位會員停用／軟刪、session 清除，正常安全紀錄保留。工程文件與全站導讀同步更新，合併前備份並核對原始內容 |

## 中斷與不穩定事件

首次跨模組執行期間 Docker Desktop 停止，Windows 引擎與 WSL Docker 均不可用，導致 502／連線中斷；恢復服務後重跑。這次中斷沒有列為通過。

既有測試協調器曾在正常請求遇到 CSRF_INVALID；本輪紀錄兩次更新後 token 期限較前次少一秒。跨模組協調器比照真正前端只更新並重試一次，刻意缺漏／偽造 header／跨站案例不重試，失敗回應亦依 Error 契約驗證；沒有修改後端 CSRF 期限或放寬保護。此觀察只說明時間欄位變化，未據此斷言原因。

優惠重新啟用／首頁下架及一次收藏新增曾出現預期回應不符，後續分別複查通過；沒有用自動重試掩蓋公開版本未就緒或修改斷言。這些間歇事件尚未取得穩定重現原因，保留於紀錄，不能據後來通過宣稱任何環境下皆無問題。容器重建工具首次誤比較 requestId，已改為只比較業務資料並重驗通過。

## 重現與後續驗收

### 2026-10-23 20:21 再次定點複驗

依使用者「驗證一次」重跑，應用程式未修改。本輪六組依序完成，沒有失敗後自動重跑整組：收藏 69、目前行程 100、五輪發布切換 60、容器重建 17、優惠 40、首頁 21，共 307 項檢查通過；四組會員整合的 CSRF 更新重試均為 0。優惠重新啟用、首頁下架與收藏新增沒有重現先前非預期回應，不能據此宣告根因已消除。首頁刻意破壞 renderer 的兩個 503 均符合預期，診斷只見測試消息標題／首頁投影與 generation 差異，復原後通過。

另重跑 PHP 69 項／270 斷言、Node 126 項、PHPStan10／PHPat、221 檔格式 dry-run、持久層 25 項、新空庫／保留資料升級與重跑，皆通過；65 頁／77 個本機 HTTP 完整性檢查零差異。原始 69 檔、CSS、.env 忽略及 852 個本機文件連結再次核對。測試庫的 p6 測試會員有效數、殘留收藏／行程及故障 trigger 均為 0。沒有重跑上輪全部 21 組既有模組契約、Composer audit 或 Rector；這些仍引用上輪證據，不能混稱本輪已執行。

本輪加掛診斷只讀取狀態／投影差異路徑，不輸出 token／密碼，也不重試業務失敗或放寬後端安全設定。證據：忽略的 artifacts/phase6-recheck-results.json、phase6-recheck-*.log 與 phase6-recheck-frontend-integrity.json。瀏覽器工具再次啟動仍為 Node runtime 路徑錯誤；本輪沒有實際桌面／手機或鍵盤畫面通過結果，階段狀態與 main 維持不變。

```powershell
docker compose --profile test --profile mail up -d
python backend/tests/member_resources_integration.py --dependency-path artifacts/audit-jsonschema
python backend/tests/member_itinerary_integration.py --dependency-path artifacts/audit-jsonschema
python backend/tests/member_resources_restart.py --dependency-path artifacts/audit-jsonschema
python backend/tests/member_publication_integration.py --dependency-path artifacts/audit-jsonschema
python backend/tests/phase6_regression.py --dependency-path artifacts/audit-jsonschema
docker compose exec -T app-test php tests/member_resources_queries.php
docker compose run --rm --no-deps frontend node --test
```

dependency-path 是 Git 忽略的本機測試依賴，Windows 與 WSL 需使用各自相容目錄；也可依 requirements.txt 安裝後省略。本輪先用 WSL 引擎，Docker Desktop 恢復後用 Windows CLI。PHP／Composer 始終在 Docker。主要本機證據位於忽略的 artifacts/：full-recheck-contract-validation.json、phase6-cross-module-results.json、phase6-query-results.json、phase6-restart-results.json 及各 phase5-*-results.json；不提交憑證或測試帳號密碼。

上列為 10-23 當時的中斷與自動化紀錄；後續實際畫面結果如下，不能將較早的待驗文字解讀為當前狀態。

## 瀏覽器驗收

2026-10-23 至 10-24，以 Brave 操作 localhost:8081 與原始 website 的本機唯讀頁。先前的 Node 啟動失敗屬工具問題；這次可用瀏覽器連線，沒有因此變更應用程式的登入或安全設定。跨導頁曾出現工具連線逾時，重新取得分頁後依頁面實際結果判斷；不把逾時列為應用程式通過證據。

| 實際流程 | 觀察結果 |
| --- | --- |
| 匿名收藏 → 登入 A → 確認 | 返回原頁帶 pendingFavorite；載入時不自動收藏，明確確認後保存。列表、我的收藏、詳情狀態同步；取消至空清單 |
| 匿名詳情加入 → 登入 A → 取消 | 返回 planner 顯示東門市場待辦，原行程仍為 0 站；取消清掉 query。再次從詳情直接加入及刷新後仍有 1 站 |
| 登入／註冊切換 | 兩向連結均保留白名單驗證後的 planner＋place；本次只檢查切換，不提交註冊或代解驗證碼。實際外站拒絕由 JS 回歸涵蓋 |
| B 分頁與下架 | 23 筆收藏以 20＋3 顯示；53 站以 50＋3 載入。下架自有景點後，行程只顯示舊公開快照及不可瀏覽；收藏用通用失效卡，沒有草稿新名稱／正文，仍可取消 |
| B 版本衝突 | 第二 API client 加入一站，舊畫面移除回 409；清掉過期站點、提示重讀、焦點移到狀態訊息，重讀成功。不覆蓋另一端結果 |
| 帳號隔離 | B 的行程頁離開後，另一分頁改登入 A，再返回行程顯示 A 與 0 站；未顯示 B 的 53 站。沒有宣稱實際使用 bfcache 快取 |
| 鍵盤與安全文字 | 收藏可由無障礙樹識別，分頁／移除後狀態可聚焦；Tab 焦點可見。長英文名稱換行，含 HTML 標籤名稱保持純文字 |
| 入口與界線 | 美食分類、景點詳情、博物館收藏、首頁開始安排、電影場次介紹 → planner 均可用；電影沒有加入站點。推薦 route query 只開 planner、不套用示意站點，側欄推薦按鈕停用 |
| 畫面對照 | 原型與實作 planner 的 hero、色彩／字體、桌面雙欄／手機單欄、站點編號與移除位置保留。名稱日期唯讀、重新載入、空狀態與收藏新頁為本階段明列差異，不聲稱完全逐像素相同 |

瀏覽器的 localhost 原有 110% 縮放，因此尺寸覆寫後另讀 innerWidth／innerHeight，實際 CSS 桌面為 1440×900、手機為 390×844；不是只相信工具要求值。修正後兩尺寸都無頁面水平溢出，手機導覽展開／關閉成功。原型頁以原本 100% 比較，未更改使用者瀏覽器縮放設定。

本輪修正及回歸：

- `content-cards.js`／`publish.mjs` 的含愛心圖片容器改為 group，避免 img 語意隱藏互動子元素；未填營業時間顯示既有「依店家公告」。`member-card-accessibility.test.mjs` 與真實發布產物測試覆蓋此項。
- `phase6.css` 限制 grid 子項最小寬度並允許長文字換行，修正手機長名稱把行程撐至 1489px 的問題；修正後 53 站均無越界。
- `pages/attractions.js` 支援美食原型沒有數字 span 的分類按鈕；food.html 的店家分類值改與 API 一致，補 tab 語意。`attraction-food-list.test.mjs` 執行真實載入程式驗證分類與共用會員控制掛載。
- `auth.js` 登入／註冊互切使用既有 returnTo 白名單；`auth-navigation.test.mjs` 覆蓋待辦保留及外站／admin 目的地拒絕。未新增自動寫入或放寬授權。

最後前端 129 項通過。PHP／MariaDB 未修改，沿用上輪列明的 307 項定點複驗及品質結果；未因前端修正宣稱又重跑所有後端測試。畫面證據保存在本機忽略的 artifacts：phase6-planner-long-mobile.jpg、phase6-favorites-page2-desktop.jpg、phase6-planner-final-mobile.jpg 及原型對照截圖；這些只含隔離測試資料。截圖不代替 API／交易或其他瀏覽器驗證。先前間歇事件仍保留，沒有據此宣稱根因已消除。

收尾重驗：建置 65 頁、77 個本機 HTTP 完整性零差異；原始 69 檔與 style.css 逐 byte 保留、.env 仍被忽略、862 個本機文件連結存在。清理後查詢測試會員有效數、收藏、行程、session、自有景點與電影均為 0；測試會員為停用／軟刪，正常安全紀錄仍保留。暫時的原型唯讀伺服器已停止，瀏覽器尺寸覆寫已還原、代理測試分頁關閉，使用者原有分頁保留。
