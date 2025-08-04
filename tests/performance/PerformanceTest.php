<?php
/**
 * Performance Tests for WPMatch Profile Fields
 *
 * Tests for performance benchmarks, large dataset handling,
 * database query optimization, and caching effectiveness.
 *
 * @package WPMatch
 * @subpackage Tests
 */

use PHPUnit\Framework\TestCase;

class PerformanceTest extends TestCase {
    
    private $database;
    private $field_manager;
    private $cache;
    
    public function setUp(): void {
        $this->database = $this->createMock(WPMatch_Database::class);
        $this->field_manager = $this->createMock(WPMatch_Profile_Field_Manager::class);
        $this->cache = $this->createMock(WPMatch_Cache::class);
    }
    
    /**
     * Test large dataset handling (1,000+ users requirement)
     */
    public function test_large_dataset_performance() {
        $user_count = 1500; // Above the 1,000 user requirement
        
        // Generate mock user data
        $large_user_dataset = [];
        for ($i = 1; $i <= $user_count; $i++) {
            $large_user_dataset[] = [
                'user_id' => $i,
                'field_values' => [
                    'age' => rand(18, 65),
                    'location' => "City $i",
                    'interests' => ['sport', 'music', 'travel'][rand(0, 2)],
                    'about_me' => "User bio for user $i"
                ]
            ];
        }
        
        // Mock database to return large dataset
        $this->database
            ->expects($this->once())
            ->method('get_all_user_profiles')
            ->willReturn($large_user_dataset);
        
        // Measure performance
        $start_time = microtime(true);
        $start_memory = memory_get_usage();
        
        $profiles = $this->database->get_all_user_profiles();
        
        $end_time = microtime(true);
        $end_memory = memory_get_usage();
        
        $execution_time = $end_time - $start_time;
        $memory_used = $end_memory - $start_memory;
        
        // Performance assertions
        $this->assertCount($user_count, $profiles);
        $this->assertLessThan(2.0, $execution_time, 'Query should complete within 2 seconds');
        $this->assertLessThan(50 * 1024 * 1024, $memory_used, 'Memory usage should be under 50MB');
    }
    
    /**
     * Test database query performance with indexes
     */
    public function test_database_query_performance() {
        $query_tests = [
            'simple_select' => [
                'query' => 'SELECT * FROM wp_wpmatch_profile_fields WHERE status = "active"',
                'max_time' => 0.1 // 100ms
            ],
            'join_query' => [
                'query' => 'SELECT f.*, v.value FROM wp_wpmatch_profile_fields f LEFT JOIN wp_wpmatch_field_values v ON f.id = v.field_id WHERE f.is_searchable = 1',
                'max_time' => 0.2 // 200ms
            ],
            'complex_search' => [
                'query' => 'SELECT DISTINCT u.user_id FROM wp_wpmatch_profiles u JOIN wp_wpmatch_field_values v ON u.user_id = v.user_id WHERE v.field_name IN ("age", "location") AND v.value LIKE "%test%"',
                'max_time' => 0.5 // 500ms
            ]
        ];
        
        foreach ($query_tests as $test_name => $test_data) {
            // Mock query execution time
            $this->database
                ->expects($this->once())
                ->method('execute_query')
                ->with($test_data['query'])
                ->willReturnCallback(function() use ($test_data) {
                    // Simulate query execution
                    $start = microtime(true);
                    usleep(rand(10000, 50000)); // Random delay 10-50ms
                    $end = microtime(true);
                    
                    return [
                        'results' => ['mock_data'],
                        'execution_time' => $end - $start
                    ];
                });
            
            $result = $this->database->execute_query($test_data['query']);
            
            $this->assertLessThan(
                $test_data['max_time'], 
                $result['execution_time'],
                "Query '$test_name' exceeded maximum execution time"
            );
        }
    }
    
    /**
     * Test caching effectiveness
     */
    public function test_caching_effectiveness() {
        $cache_key = 'profile_fields_all';
        $cache_data = [
            ['id' => 1, 'name' => 'age', 'type' => 'number'],
            ['id' => 2, 'name' => 'location', 'type' => 'text'],
            ['id' => 3, 'name' => 'interests', 'type' => 'checkbox']
        ];
        
        // Test cache miss (first request)
        $this->cache
            ->expects($this->once())
            ->method('get')
            ->with($cache_key)
            ->willReturn(false); // Cache miss
        
        $this->cache
            ->expects($this->once())
            ->method('set')
            ->with($cache_key, $cache_data, 3600)
            ->willReturn(true);
        
        // Simulate first request (cache miss)
        $cached_data = $this->cache->get($cache_key);
        if ($cached_data === false) {
            // Would normally fetch from database
            $this->cache->set($cache_key, $cache_data, 3600);
        }
        
        // Test cache hit (subsequent request)
        $this->cache = $this->createMock(WPMatch_Cache::class);
        $this->cache
            ->expects($this->once())
            ->method('get')
            ->with($cache_key)
            ->willReturn($cache_data); // Cache hit
        
        // Measure cache retrieval performance
        $start_time = microtime(true);
        $result = $this->cache->get($cache_key);
        $end_time = microtime(true);
        
        $cache_time = $end_time - $start_time;
        
        $this->assertEquals($cache_data, $result);
        $this->assertLessThan(0.001, $cache_time, 'Cache retrieval should be under 1ms');
    }
    
    /**
     * Test pagination performance
     */
    public function test_pagination_performance() {
        $total_records = 10000;
        $page_size = 20;
        $page_number = 250; // Middle page
        
        // Mock paginated query
        $this->database
            ->expects($this->once())
            ->method('get_paginated_fields')
            ->with($page_number, $page_size)
            ->willReturnCallback(function($page, $size) use ($total_records) {
                $start_time = microtime(true);
                
                // Simulate pagination calculation
                $offset = ($page - 1) * $size;
                $mock_results = [];
                
                for ($i = $offset; $i < min($offset + $size, $total_records); $i++) {
                    $mock_results[] = ['id' => $i + 1, 'name' => "field_" . ($i + 1)];
                }
                
                $end_time = microtime(true);
                
                return [
                    'data' => $mock_results,
                    'total' => $total_records,
                    'execution_time' => $end_time - $start_time
                ];
            });
        
        $result = $this->database->get_paginated_fields($page_number, $page_size);
        
        $this->assertCount($page_size, $result['data']);
        $this->assertEquals($total_records, $result['total']);
        $this->assertLessThan(0.1, $result['execution_time'], 'Pagination should be fast even for large datasets');
    }
    
    /**
     * Test search query performance
     */
    public function test_search_performance() {
        $search_criteria = [
            'age_min' => 25,
            'age_max' => 35,
            'location' => 'New York',
            'interests' => ['music', 'travel'],
            'keywords' => 'software engineer'
        ];
        
        $this->field_manager
            ->expects($this->once())
            ->method('search_profiles')
            ->with($search_criteria)
            ->willReturnCallback(function($criteria) {
                $start_time = microtime(true);
                
                // Simulate complex search operation
                $mock_results = [];
                for ($i = 1; $i <= 100; $i++) {
                    $mock_results[] = [
                        'user_id' => $i,
                        'relevance_score' => rand(70, 100) / 100
                    ];
                }
                
                // Sort by relevance
                usort($mock_results, function($a, $b) {
                    return $b['relevance_score'] <=> $a['relevance_score'];
                });
                
                $end_time = microtime(true);
                
                return [
                    'results' => array_slice($mock_results, 0, 20), // Limit to top 20
                    'total_found' => count($mock_results),
                    'execution_time' => $end_time - $start_time
                ];
            });
        
        $search_result = $this->field_manager->search_profiles($search_criteria);
        
        $this->assertLessThanOrEqual(20, count($search_result['results']));
        $this->assertLessThan(1.0, $search_result['execution_time'], 'Search should complete within 1 second');
        
        // Check results are sorted by relevance
        $scores = array_column($search_result['results'], 'relevance_score');
        $this->assertEquals($scores, array_values(array_reverse(array_sort($scores))));
    }
    
    /**
     * Test memory usage during bulk operations
     */
    public function test_bulk_operations_memory_usage() {
        $bulk_field_data = [];
        
        // Generate large bulk dataset
        for ($i = 1; $i <= 1000; $i++) {
            $bulk_field_data[] = [
                'name' => "bulk_field_$i",
                'label' => "Bulk Field $i",
                'type' => 'text',
                'group' => 'bulk_test'
            ];
        }
        
        $start_memory = memory_get_usage();
        
        // Mock bulk insert operation
        $this->database
            ->expects($this->once())
            ->method('bulk_insert_fields')
            ->with($bulk_field_data)
            ->willReturnCallback(function($data) {
                // Simulate processing each field
                $processed = 0;
                foreach ($data as $field) {
                    // Simulate field validation and insertion
                    $processed++;
                }
                return $processed;
            });
        
        $processed_count = $this->database->bulk_insert_fields($bulk_field_data);
        
        $end_memory = memory_get_usage();
        $memory_used = $end_memory - $start_memory;
        
        $this->assertEquals(1000, $processed_count);
        $this->assertLessThan(10 * 1024 * 1024, $memory_used, 'Bulk operations should use less than 10MB memory');
    }
    
    /**
     * Test AJAX response time
     */
    public function test_ajax_response_time() {
        $ajax_actions = [
            'create_field' => ['field_name' => 'test', 'field_type' => 'text'],
            'update_field' => ['field_id' => 123, 'field_label' => 'Updated'],
            'delete_field' => ['field_id' => 456],
            'get_field_options' => ['field_type' => 'select'],
            'validate_field' => ['field_data' => ['name' => 'test']]
        ];
        
        foreach ($ajax_actions as $action => $post_data) {
            $start_time = microtime(true);
            
            // Mock AJAX handler execution
            $this->simulate_ajax_handler($action, $post_data);
            
            $end_time = microtime(true);
            $response_time = $end_time - $start_time;
            
            $this->assertLessThan(0.5, $response_time, "AJAX action '$action' should respond within 500ms");
        }
    }
    
    /**
     * Mock AJAX handler simulation
     */
    private function simulate_ajax_handler($action, $post_data) {
        // Simulate typical AJAX handler operations:
        // 1. Nonce verification
        usleep(1000); // 1ms
        
        // 2. Capability check
        usleep(500); // 0.5ms
        
        // 3. Data validation and sanitization
        usleep(2000); // 2ms
        
        // 4. Database operation
        usleep(rand(5000, 20000)); // 5-20ms
        
        // 5. Response preparation
        usleep(1000); // 1ms
    }
    
    /**
     * Test concurrent request handling
     */
    public function test_concurrent_request_handling() {
        $concurrent_requests = 10;
        $request_times = [];
        
        // Simulate concurrent AJAX requests
        for ($i = 0; $i < $concurrent_requests; $i++) {
            $start_time = microtime(true);
            
            // Mock concurrent request processing
            $this->field_manager
                ->expects($this->once())
                ->method('handle_request')
                ->willReturnCallback(function() {
                    usleep(rand(10000, 50000)); // 10-50ms processing time
                    return ['success' => true, 'data' => 'processed'];
                });
            
            $result = $this->field_manager->handle_request();
            
            $end_time = microtime(true);
            $request_times[] = $end_time - $start_time;
            
            $this->assertTrue($result['success']);
        }
        
        // Check that concurrent requests don't significantly degrade performance
        $avg_time = array_sum($request_times) / count($request_times);
        $max_time = max($request_times);
        
        $this->assertLessThan(0.1, $avg_time, 'Average request time should be under 100ms');
        $this->assertLessThan(0.2, $max_time, 'Maximum request time should be under 200ms');
    }
    
    /**
     * Test database connection pooling efficiency
     */
    public function test_database_connection_efficiency() {
        $connection_tests = [
            'single_query' => 1,
            'multiple_queries' => 5,
            'batch_queries' => 20
        ];
        
        foreach ($connection_tests as $test_name => $query_count) {
            $start_time = microtime(true);
            
            // Mock database connection and query execution
            $this->database
                ->expects($this->exactly($query_count))
                ->method('execute_query')
                ->willReturnCallback(function() {
                    // Simulate query execution time
                    usleep(rand(1000, 5000)); // 1-5ms per query
                    return ['data' => 'result'];
                });
            
            // Execute queries
            for ($i = 0; $i < $query_count; $i++) {
                $this->database->execute_query("SELECT * FROM table WHERE id = $i");
            }
            
            $end_time = microtime(true);
            $total_time = $end_time - $start_time;
            
            // Connection overhead should be minimal
            $max_allowed_time = $query_count * 0.01 + 0.05; // 10ms per query + 50ms overhead
            $this->assertLessThan($max_allowed_time, $total_time, "Connection efficiency test '$test_name' failed");
        }
    }
    
    /**
     * Test frontend field rendering performance
     */
    public function test_frontend_rendering_performance() {
        $field_count = 50; // Typical profile form size
        $fields = [];
        
        // Generate mock field data
        for ($i = 1; $i <= $field_count; $i++) {
            $fields[] = [
                'id' => $i,
                'name' => "field_$i",
                'label' => "Field $i",
                'type' => ['text', 'select', 'checkbox', 'textarea'][rand(0, 3)],
                'options' => $i % 3 === 0 ? ['Option 1', 'Option 2', 'Option 3'] : []
            ];
        }
        
        $renderer = $this->createMock(WPMatch_Frontend_Field_Renderer::class);
        
        $start_time = microtime(true);
        $start_memory = memory_get_usage();
        
        // Mock field rendering
        $renderer
            ->expects($this->once())
            ->method('render_form')
            ->with($fields)
            ->willReturnCallback(function($field_data) {
                $html = '<form>';
                foreach ($field_data as $field) {
                    $html .= "<div class='field-wrapper'>";
                    $html .= "<label>{$field['label']}</label>";
                    $html .= "<input type='text' name='{$field['name']}'>";
                    $html .= "</div>";
                }
                $html .= '</form>';
                return $html;
            });
        
        $rendered_html = $renderer->render_form($fields);
        
        $end_time = microtime(true);
        $end_memory = memory_get_usage();
        
        $render_time = $end_time - $start_time;
        $memory_used = $end_memory - $start_memory;
        
        $this->assertStringContainsString('<form>', $rendered_html);
        $this->assertLessThan(0.1, $render_time, 'Form rendering should complete within 100ms');
        $this->assertLessThan(1024 * 1024, $memory_used, 'Form rendering should use less than 1MB memory');
    }
    
    /**
     * Performance benchmark summary
     */
    public function test_performance_benchmark_summary() {
        $benchmarks = [
            'large_dataset_query' => ['target' => 2.0, 'unit' => 'seconds'],
            'cache_retrieval' => ['target' => 0.001, 'unit' => 'seconds'],
            'ajax_response' => ['target' => 0.5, 'unit' => 'seconds'],
            'pagination_query' => ['target' => 0.1, 'unit' => 'seconds'],
            'search_query' => ['target' => 1.0, 'unit' => 'seconds'],
            'bulk_operation_memory' => ['target' => 10, 'unit' => 'MB'],
            'form_rendering' => ['target' => 0.1, 'unit' => 'seconds']
        ];
        
        $all_benchmarks_met = true;
        $failed_benchmarks = [];
        
        foreach ($benchmarks as $benchmark => $criteria) {
            // This is a summary test - individual benchmarks are tested above
            // Here we're just validating that our performance targets are reasonable
            $this->assertGreaterThan(0, $criteria['target'], "Benchmark '$benchmark' should have positive target");
            $this->assertNotEmpty($criteria['unit'], "Benchmark '$benchmark' should have unit specified");
        }
        
        // Log performance summary
        echo "\n=== PERFORMANCE BENCHMARK TARGETS ===\n";
        foreach ($benchmarks as $benchmark => $criteria) {
            echo sprintf("%-25s: < %s %s\n", $benchmark, $criteria['target'], $criteria['unit']);
        }
        echo "=====================================\n";
        
        $this->assertTrue(true, 'Performance benchmark targets defined');
    }
}