# JSON-LD單一擁有者

2026-10-31，階段十二12A。S：功能規格E31:G31/L31、SEO規格B13:F13；策略E-34，細節見[計畫](../PHASE-12-PLAN.md)。

`frontend/assets/js/core/structured-data.js`是Browser／Node共用純factory；`frontend/scripts/structured-data.mjs`只重匯出作發布入口。從已發布context及SEO結果建立WebSite／Organization／WebPage及mainEntity：Museum、TouristAttraction、美食／在地店家LocalBusiness、Movie、Article、適用Event、Offer，不查DB、輸出HTTP或讀瀏覽器身分。

`seo-head.mjs`唯一擁有script，移除原型／舊JSON-LD後安全序列化一份。`seo-resolver.js`共用保護頁／可索引判定；build不把示範文章當真實已發布資料，publisher才傳published=true。`NodeContentRenderer.php`把模組納指紋，調整即重建generation。

`list-seo.js`在分類、分頁、返回時復用同一factory更新已有graph，與canonical／標題／頁碼一致；noindex移除，不另建一份。`public-list-seo.test.mjs`執行真控制器與回歸情境。

正文、海報、地址及日期取可見欄位，不拿SEO覆寫、OG圖或Google簡介冒充正文，不猜導演／評分／作者／價格／地址。缺活動開始或場地用Article，不新增必填。時間及安全JSON函式共用，後續lastmod復用；私人、noindex及佔位無graph。

`frontend/tests/structured-data.test.mjs`驗七種內容、白名單／可見欄位、script攻擊、私有圖片、日期及原型清除。

```powershell
docker compose run --rm --no-deps frontend node --test tests/structured-data.test.mjs
```

參考[Schema.org](https://schema.org/)與[Google可見真實內容政策](https://developers.google.com/search/docs/appearance/structured-data/sd-policies)。合法Schema不保證Google特定Rich Result。12A先交付graph；XML／robots及整合另依12B／12C，不提前標完成。
