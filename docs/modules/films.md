# 電影與放映場次閱讀指南

更新：2026-10-19。階段四實作中，目前完成資料層準備（4A）；電影 API、介面與公開發布尚未接入，不能據此宣告可在網站管理電影。

## 已建立的資料層

| 檔案 | 用途 |
| --- | --- |
| `backend/database/migrations/010_films_and_screenings.sql` | 電影、場次及必要外鍵／索引；開始時間存 UTC，沒有結束時間／場地欄位 |
| `backend/database/migrations/011_film_aliases.sql` | 保存電影歷史網址與目標電影的關係 |
| `backend/src/DTO/ContentUrl.php` | 共用外連協定／格式邊界，不下載任意網址 |
| `backend/src/DTO/FilmInput.php` | 電影可寫欄位、型別及省略保留語意；新增以草稿為預設 |
| `backend/src/DTO/ScreeningInput.php` | 放映開始時間、分鐘精度、UTC 正規化及票務外連 |
| `backend/src/DTO/Film.php`、`FilmScreening.php`、`FilmPage.php`、`FilmWriteResult.php` | 管理／公開安全輸出、分頁及保存與同步結果 |
| `backend/src/Policies/ScreeningStartPolicy.php` | U-16 的同一開始分鐘／重複 ID 檢查，不計算片長 |
| `backend/src/Repositories/FilmRepository.php` | 電影 SQL、版本與別名、公開投影、場次批次映射 |
| `backend/src/Repositories/FilmScreeningRepository.php` | 場次歸屬、批次載回、整組保存及跨電影開始時間查詢；交易由 Service 協調 |
| `backend/tests/Unit/FilmInputTest.php` | 原型必填／選填、時區同時、非法日期、結束欄位拒絕及巢狀錯誤 |
| `backend/tests/film_persistence.php` | 限 yinghua_test，真實保存、歸屬、防止挪用及下架釋放；全部回滾 |

來源：Excel 功能規格 7／21、資料表建議 12／13；U-16 指定只比較日期與開始時間，結束時間延後。具體表名、DTO、索引及分鐘精度為 E-25，完整計畫見 [PHASE-4-PLAN.md](../PHASE-4-PLAN.md)。

`screenings` 省略代表保留，明確空陣列代表移除；父電影的 version 是整組更新的版本。同名電影可以存在，不能用名稱定位。場次保存必須由未來 Service 在共同發布寫入鎖及同一交易內呼叫，不把 Repository 的一次預查當成完整並行保護。

## 實際驗證

4A：主庫／測試庫新增兩份 migration 並重跑成功，事前備份留在被忽略的 artifacts。Docker PHPStan level 10／PHPat 通過；PHPUnit 52 項／227 斷言；隔離 MariaDB 資料層 8 項通過並回滾。目前不新增電影公開／管理路由，既有 45 個 API 不變。
