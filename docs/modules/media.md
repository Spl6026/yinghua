# 圖片上傳與存取閱讀指南

更新：2026-10-19。功能規格的圖片／alt／管理授權要求為 S；8 MiB、6000 邊長、1200 萬像素、重新編碼與 volume 拆分為 E-18 工程選擇；階段四依 E-25 增加電影模組隔離。

入口為 MediaController：multipart 轉 UploadedImage，讀取 action、alt、選填 source／license 及 module，再交 MediaManagement。Service 依 module 的 create／edit 驗權；省略 module 仍為 attractions。LocalImageStorage 驗證真實 PHP 上傳、檔案大小、finfo、尺寸及 GD 解碼，只接受 JPEG／PNG／WebP。重新編碼去除原始中繼資料，使用隨機 UUID 檔名及固定副檔名，以暫存檔 rename 保存。

MediaManagement 在資料庫交易內再次確認管理員權限，透過 MediaRepository 保存 media_assets 及稽核。若 DB 保存失敗則移除剛建立的檔案，不留下成功假象。source／license 可省略／空白，是管理員填寫的來源紀錄，不代表系統替內容取得法律授權。

圖片保存在 `/var/www/media` 的 media_data volume；Nginx 只讀掛載，不交 PHP 解譯。路徑僅接受指定 UUID＋副檔名。MediaAccess 對匿名使用者只放行目前成功發布快照引用的圖片；草稿或未引用圖片須有有效管理員 session 及本模組查看／新增／編輯權限。未授權讀取回 404，唯讀模擬仍不能上傳。

前端新增／編輯表單先選圖片、填寫 alt／來源／授權，按「上傳圖片」，顯示伺服器返回的預覽與尺寸；再按「儲存內容」才套用到景點。選了新圖片卻未上傳時會阻止保存，避免誤以為已替換。API client 用 FormData，讓瀏覽器建立 multipart boundary。

正式及隔離測試環境各有 media／publication volume，測試不改正式素材。重建容器不會刪除 volume；不要執行 down -v 作為一般更新。新空 volume 的目錄擁有者由 image 建置為 www-data；手動 CLI 發布同樣使用 www-data。

驗證涵蓋真實 multipart、重編碼、SVG／偽 JPEG／無 alt／尺寸超限拒絕、草稿匿名拒絕、發布後匿名可讀、越權及 CSRF。瀏覽器也實際經原生圖片選擇器選檔、上傳、預覽、儲存、發布並閱讀。細節見 [驗收記錄](../PHASE-3-ACCEPTANCE.md)。

## 圖片說明及原型資料的修正

R3-01：既有 POST／PUT attractions 現在接收 imageAlt／imageSource／imageLicense。欄位保存到各景點自己的欄位，未提供時保留，空白或 null 回到原資產說明；不改共用 media_assets。新增及編輯表單的說明可直接保存，不需要重新上傳圖片。上傳成功後帶入伺服器的正規化說明，和 mediaId 一起保存。

R3-02：recommendationLabel 是原型的「城市記憶／巷弄探索」等文案。資料庫、SSR 與 JS 卡片讀同一欄位；預設空白才顯示在地推薦。公開排序使用 publish_at DESC、id ASC，同一時間的六筆受控 seed 保持原型順序；後台清單恢復營業時間欄。

既有資料升級使用 003_attraction_review.sql，不改已套用的 SQL。新增座標約束明確要求兩者皆空或皆非空且合法；不合法的既存資料會阻止 DDL，不默默刪改。

更新後需重啟常駐 publisher，並以 www-data 執行 content:publish。舊展示資料標籤先執行 content:backfill-demo-labels 預覽，再加 --apply；只有未人工編寫且仍符合受控來源的展示筆會被補上，原始素材授權仍須確認。

## U-14 的掃描範圍

使用者明確選擇維持圖檔內容檢查：MIME、尺寸／大小、實際解碼與重新編碼。沒有獨立病毒掃描服務，不宣稱已做病毒掃描。圖片本身選填；上傳時 alt 必填，來源與授權說明不作非空門檻。

## 階段四的電影海報共用（E-25）

POST /api/v1/admin/media 可提供 `module=films`；省略仍預設 attractions。MediaModule enum 與 MediaManagement 依所選模組及 action=create/edit 授權；GET 清單驗相應模組 view，草稿 /media 預覽依資產實際 module 驗 view/create/edit。匿名僅可讀當前成功快照中的景點／電影引用圖片，不因有景點權限就開放電影草稿。

圖片格式、尺寸、8 MiB 與內容檢查仍沿用原機制，掃描範圍維持 U-14，不新增病毒掃描服務。電影用途 alt／來源／授權在 films 覆寫，不改共用資產；外部海報 URL 只交給瀏覽器，不由 PHP 任意下載。完整檔案與驗證見 [電影指南](films.md)。
