# 行程共用資料層與版本準備

2026-10-31；階段十一11A。來源為功能規格E15/G15/L15、資料表建議C19:D20（D）及E-33。沿用既有migration016的兩張表，沒有新增欄位或搬移資料。

`TripInput`／`TripStopInput`是輸入DTO，將整份標題日期及陣列站點轉成明確型別；`TripView`／`TripCreated`安全輸出，不把內部memberId變成客戶端授權參數。`Input::objects`可由呼叫端指定容量，其他既有呼叫預設100不變。空名稱日期／時間及空行程不增加業務必填；UUID、HH:mm、200字名稱、2000字備註、1000站點及既有64KiB JSON均為E-33表示／容量設定。

`ItineraryRepository`集中本人集合、全量站點保存、選定與軟刪SQL；Service須先鎖會員並建立交易。`nextVersion`取同會員全部歷史行程的MAX+1，切換目前行程時不重用舊版本，避免只傳version的P0舊分頁修改新current。`saveOwned`保留站點DTO提供的id及snapshot_name，先刪子列再依陣列順序寫回，全程由上層交易保護。刪目前行程選另一有效行程，最後一份則沒有current。

`MemberAttractions::requireAvailableNames`一次讀成功投影、固定ID次序鎖景點；完整行程不為每站重讀投影，原單筆呼叫亦共用此方法。既有不可用站點如何保留由上層Service處理，此方法只驗需要新加入的站點。

DTO驗證見`backend/tests/Unit/TripInputTest.php`。PHP／品質工具在Docker執行：

```powershell
docker compose exec -T app-test /var/www/tools/vendor/bin/phpunit -c phpunit.xml
docker compose exec -T app-test /var/www/tools/vendor/bin/phpstan analyse -c phpstan.neon --no-progress
```

11A只交付共用資料及型別能力，不把尚未接入的HTTP／畫面標為完成；後續交付依[階段十一計畫](../PHASE-11-PLAN.md)。
