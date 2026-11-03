# 階段十二安全審查

2026-10-31，使用security-audit，範圍為JSON-LD、爬蟲索引及必要公開投影，不新增認證或管理權限。

| 邊界 | 審查／驗證 |
| --- | --- |
| 公私隔離 | 只收成功公開快照；private／admin／search／noindex／佔位無graph且不進XML。舊slug、內部路徑、草稿、下架排除；HTTP、Node、Browser皆驗。 |
| 注入 | JSON序列化後編碼script終止及Unicode分隔字元；XML獨立編碼。惡意名稱測試、JSON解析及官方Validator通過。 |
| URL／SSRF | canonical取已驗證設定及正規路徑，不讀Host，不讓metadata驅動任意伺服器抓取。只接受無帳密HTTP(S)同來源索引；圖片取可見公開白名單。無新HTTP客戶端。 |
| 白名單 | 只取可見名稱／介紹／圖片／地址／日期，不輸出整個record。indexModifiedAt只取已發布DTO；原104 API／components不變，無會員／草稿／帳號資料。 |
| 同版／失敗 | 所有HTML／graph／XML／robots完成才啟用current；不同版503。自有錯誤slug造成發布失敗、舊資源503及復原已真實驗證。 |
| 容量 | URL與UTF8位元組有標準容量，超量完整分片不截斷；真50,001筆純函式驗證，不造價格／評分或新增業務必填。 |
| 授權／robots | robots排admin，API繼續session／CSRF／本人／權限，不以robots授權。P1 255及P0收藏69項含失敗／越權／並行回歸。 |
| 環境／秘密 | 無新依賴、服務、env或DDL。只用自有yinghua_test資料，最後操作者仍是自有帳號才復原SEO原列。未改root／主庫；Validator僅提交公開graph，不傳Cookie、token或會員資料。 |

PHP107／356、Node224、PHPStan／PHPat、7檔PER-CS零差異；369索引整合、181 P1契約與官方Schema.org七型別無錯誤／警告。本次範圍無新增待處理安全失敗，不是正式server滲透測試。未重跑全量Semgrep、Gitleaks、映像／依賴掃描，既有正式交接差項保留；不將工具安裝當通過。證據與AA界線見[驗收](PHASE-12-ACCEPTANCE.md)。
