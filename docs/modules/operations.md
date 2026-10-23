# 部署與運維閱讀指南

更新：2026-10-24。來源是階段八 E-29；具體診斷／時限不是業主新增的業務限制。實作進行中，正式上線與完整 P0 尚未驗收。

## 8A 穩定性診斷

`backend/src/Http/Csrf.php` 保留簽章、身分命名空間、Cookie 綁定、Origin 與到期拒絕。原本「到期時間不可超過現在加 TTL」在時鐘回撥一秒時也拒絕剛簽發的 token；現在僅對未來上限容許五秒校時差，到期下限不延長。一次驗證只讀一次時鐘。`backend/tests/Unit/CsrfTest.php` 以真簽章／可控 Clock 驗同秒、回撥一／五／六秒、到期與其他安全拒絕。不是關閉 CSRF 或讓前端無限重送。

拒絕日誌只有 `request=<id> csrf_reason=<有限原因>`：origin、anonymous-cookie、format、expired、future、signature；不記個資、Cookie、token 或 body。配合 Nginx requestId 判斷流程，不能從不同 requestId 推定同一使用者。

`backend/tests/stability_observation.py` 固定100輪 csrf GET → 空註冊 POST，預期422且不自動重試；僅寫 requestId／狀態／錯誤碼／到期時間差／耗時到忽略的 artifacts。2026-10-24 修正前100輪均為422，沒有回撥；受控測試另證明回撥邊界。歷史未捕捉回應的註冊失敗仍不能直接認定原因，整合觀察尚未結束。

測試站沒有常駐 publisher。這次其公開首頁503在執行 `docker compose exec -T -u www-data app-test php bin/console.php content:tick` 後恢復，測試容器 health 從 unhealthy 轉 healthy；這只能證明當次待發布可復原，不將所有歷史 PUBLICATION_PENDING 都歸同因。

命令：`docker compose exec -T app php /var/www/tools/vendor/bin/phpunit -c phpunit.xml --filter CsrfTest`；`python backend/tests/stability_observation.py`。Python 使用主機既有 runtime，PHP 僅在 Docker 執行。
