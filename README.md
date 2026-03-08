# 學術資料全文檢索系統 (Academic Search Engine)

本專案是一個基於 Docker 容器化架構開發的全文檢索系統，旨在處理約 20,000 筆學術資料的高效檢索與展示

## 1. 目前架構 (Current Architecture)

本系統採用微服務架構，透過 `docker-compose` 進行容器編排，整合以下三大核心組件：

* **Web Server (Nginx)**：作為反向代理伺服器，負責處理 HTTP 請求並將 PHP 請求轉發至後端處理
* **Search Engine (Elasticsearch 8.x)**：核心搜尋引擎，負責 20,000 筆學術資料的索引儲存、全文檢索與關鍵字高亮 (Highlighting)
* **Backend Runtime (PHP-FPM)**：負責執行業務邏輯，透過 Elasticsearch 官方 Client 串接 API，並處理資料分頁與前端渲染

## 2. 程式碼功能說明 (Codebase Overview)

* **`docker-compose.yml`**：定義系統的基礎設施，包含 Nginx、PHP 與 Elasticsearch 容器的網路連結、磁碟掛載 (Volumes) 與資源限制
* **`nginx.conf`**：配置網頁伺服器的路由規則，確保支援 PHP 檔案解析並優化靜態資源載入
* **`html/` 目錄**：
    * 存放搜尋引擎前端介面 (Bootstrap 5)
    * 包含 PHP 檢索邏輯，實作 RESTful API 呼叫
* **`data/` 目錄**：存放原始 JSON/CSV 學術資料集，供系統初始化索引使用
* **`vendor/`**：透過 Composer 管理的 PHP 依賴套件（如 Elasticsearch PHP Client）

## 3. 系統界面展示 (System Interface Demo)

本系統提供直覺且響應式的網頁介面，支援多種學術類別篩選與精確檢索：

### (1) 系統首頁與搜尋畫面
* **功能描述**：提供簡潔的搜尋框與學術類別（如 Background, Methods 等）快速篩選按鈕
* **技術特點**：使用 Bootstrap 5 進行排版，具備即時日期顯示與響應式按鈕佈局

![Home Page](images/home.png)

### (2) 全文檢索與結果高亮 (Highlighting)
* **功能描述**：搜尋後呈現論文標題、作者與分類。針對匹配到的關鍵字，利用 Elasticsearch 的 Highlighting 功能進行黃底高亮
* **技術特點**：後端 PHP 串接 Elasticsearch RESTful API，並解析回傳的標籤進行渲染

![Search Results](images/results.png)

### (3) 頁數呈現與分頁功能
* **功能描述**：針對 20,000 筆資料量，下方提供完整的翻頁機制、跳頁功能與總結果筆數顯示
* **技術特點**：透過 Elasticsearch `from` 與 `size` 參數實作高效分頁邏輯

![Pagination](images/pagination.png)

## 4. 目前開發進度 (Current Status)

* **運行環境**：目前系統已在 **Localhost (本地端)** 環境透過 Docker 穩定運行
* **功能實作**：
    * 已完成 20,000 筆資料的 Bulk 匯入與索引建立
    * 實作具備分頁功能與檢索結果高亮的 Web UI

## 5. 未來展望 (Future Roadmap)

* **雲端伺服器部署**：計畫將系統部署至 **Oracle Cloud Infrastructure (OCI)**
* **硬體資源優化**：預計採用 **ARM-based (Ampere A1)** 規格，配置 **4 OCPU / 24GB RAM** 以因應 Elasticsearch 較高的記憶體需求
* **運維挑戰克服**：
    * 針對目前遇到的 `Out of capacity` 資源限制，已啟動 **PAYG (Pay-As-You-Go)** 帳戶升級流程，爭取更高優先權的資源調度
    * 計畫實作 VCN (虛擬雲端網路) 安全性規則與 SSH Key 存取控管