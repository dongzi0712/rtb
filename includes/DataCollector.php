<?php
class DataCollector {
    private $db;
    private $cookie_file;
    
    public function __construct($db) {
        $this->db = $db;
        $this->cookie_file = dirname(__DIR__) . '/storage/cookies.txt';
        
        // 确保存储目录存在
        if (!is_dir(dirname($this->cookie_file))) {
            mkdir(dirname($this->cookie_file), 0755, true);
        }
    }
    
    public function collect($url, $uid, $start_date, $end_date) {
        try {
            // 解析URL参数
            $query = parse_url($url, PHP_URL_QUERY);
            parse_str($query ?? '', $params);
            
            $all_items = [];
            $page = 1;
            $has_more = true;
            
            while ($has_more) {
                // 构建请求参数
                $data = [
                    'bs' => $uid,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'page' => $page,
                    'page_size' => 100
                ];
                
                // 合并URL中的其他参数
                $data = array_merge($params, $data);
                
                // 发送请求
                $response = $this->makeRequest('https://dt.bd.cn/main/quark_list', $data);
                
                if (!$response) {
                    throw new Exception('网络请求失败');
                }
                
                $result = json_decode($response, true);
                if (!$result || !isset($result['code']) || $result['code'] !== 1) {
                    throw new Exception($result['msg'] ?? '接口返回错误');
                }
                
                $items = $result['data']['list'] ?? [];
                $all_items = array_merge($all_items, $items);
                
                // 检查是否还有更多数据
                $total = $result['data']['total'] ?? 0;
                $has_more = count($all_items) < $total;
                $page++;
                
                // 防止无限循环
                if ($page > 10) {
                    break;
                }
                
                // 添加延迟，避免请求过快
                usleep(500000); // 500ms
            }
            
            // 保存数据
            $this->saveData($uid, $all_items);
            
            // 记录UID
            $this->recordUid($uid);
            
            // 保存设置
            $this->saveSettings($url, $uid);
            
            return [
                'success' => true,
                'data' => [
                    'total' => count($all_items),
                    'start_date' => $start_date,
                    'end_date' => $end_date
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function makeRequest($url, $data) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_COOKIEFILE => $this->cookie_file,
            CURLOPT_COOKIEJAR => $this->cookie_file,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With: XMLHttpRequest',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ]
        ]);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("CURL错误: $error");
        }
        
        return $response;
    }
    
    private function saveData($uid, $items) {
        $stmt = $this->db->prepare("
            INSERT INTO data_records 
            (uid, date_str, mobile_new, pc_new, saves, mobile_income, pc_income)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            mobile_new = VALUES(mobile_new),
            pc_new = VALUES(pc_new),
            saves = VALUES(saves),
            mobile_income = VALUES(mobile_income),
            pc_income = VALUES(pc_income)
        ");
        
        foreach ($items as $item) {
            $stmt->execute([
                $uid,
                $item['date_str'],
                (int)$item['mobile_count'],
                (int)$item['pc_count'],
                (int)$item['store_count'],
                (float)$item['mobile_fencheng'],
                (float)$item['pc_fencheng']
            ]);
        }
    }
    
    private function recordUid($uid) {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO uids (uid, created_by)
            VALUES (?, ?)
        ");
        
        $stmt->execute([$uid, $_SESSION['user_id']]);
    }
    
    public function saveSettings($url, $uid) {
        $stmt = $this->db->prepare("
            INSERT INTO uid_settings (uid, url, created_by)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
            url = VALUES(url)
        ");
        
        $stmt->execute([$uid, $url, $_SESSION['user_id']]);
    }
} 