#!/bin/bash
# Test Feature #43: Handles concurrent requests

BASE_URL="http://localhost:8080/convert.php"
OUTPUT_DIR="assets/media/rapidhtml2png"
TEMP_DIR="/tmp/feature_43_test_$$"

echo "=========================================="
echo "Feature #43: Concurrent Request Handling"
echo "=========================================="
echo ""

# Create temp directory for results
mkdir -p "$TEMP_DIR"

# Test data
declare -a HTML_CONTENTS=(
    '<div style="color: red;">Concurrent Test 1</div>'
    '<div style="color: blue;">Concurrent Test 2</div>'
    '<div style="color: green;">Concurrent Test 3</div>'
    '<div style="color: purple;">Concurrent Test 4</div>'
    '<div style="color: orange;">Concurrent Test 5</div>'
)

echo "Test Configuration:"
echo "  Base URL: $BASE_URL"
echo "  Concurrent Requests: ${#HTML_CONTENTS[@]}"
echo ""

# Function to send a single request
send_request() {
    local id=$1
    local html=$2
    local output_file="$TEMP_DIR/response_$id.json"

    local start_time=$(date +%s%3N)  # milliseconds
    local http_code=$(curl -s -o "$output_file" -w "%{http_code}" -X POST \
        -H "Content-Type: application/json" \
        -d "{\"html_blocks\":[\"$html\"],\"css_url\":null}" \
        "$BASE_URL" 2>/dev/null)
    local end_time=$(date +%s%3N)
    local duration=$((end_time - start_time))

    # Extract hash from response
    local hash=$(grep -o '"content_hash":"[^"]*' "$output_file" | cut -d'"' -f4 2>/dev/null)
    local success="false"
    if [ "$http_code" = "200" ]; then
        success="true"
    fi

    echo "$id|$http_code|$duration|$hash|$success"
}

# Test 1: Sequential requests (baseline)
echo "Test 1: Sequential Requests (Baseline)"
echo "------------------------------------------"

sequential_start=$(date +%s%3N)
declare -a sequential_results

for i in "${!HTML_CONTENTS[@]}"; do
    html="${HTML_CONTENTS[$i]}"
    result=$(send_request $((i+1)) "$html")
    IFS='|' read -r req_id http_code duration hash success <<< "$result"
    sequential_results+=("$http_code")
    echo "  Request $req_id: HTTP $http_code, ${duration}ms, hash=$hash"
done

sequential_end=$(date +%s%3N)
sequential_total=$((sequential_end - sequential_start))
echo "  Total Time: ${sequential_total}ms"
echo ""

# Test 2: Concurrent requests (background processes)
echo "Test 2: Concurrent Requests"
echo "------------------------------------------"

concurrent_start=$(date +%s%3N)
declare -a pids

# Launch all requests in background
for i in "${!HTML_CONTENTS[@]}"; do
    html="${HTML_CONTENTS[$i]}"
    send_request $((i+1)) "$html" > "$TEMP_DIR/concurrent_$i.txt" 2>/dev/null &
    pids+=($!)
done

# Wait for all to complete
for pid in "${pids[@]}"; do
    wait $pid
done

concurrent_end=$(date +%s%3N)
concurrent_total=$((concurrent_end - concurrent_start))

# Collect concurrent results
declare -a concurrent_hashes
declare -a concurrent_http_codes

for i in "${!HTML_CONTENTS[@]}"; do
    result=$(cat "$TEMP_DIR/concurrent_$i.txt")
    IFS='|' read -r req_id http_code duration hash success <<< "$result"
    concurrent_hashes+=("$hash")
    concurrent_http_codes+=("$http_code")
    echo "  Request $req_id: HTTP $http_code, ${duration}ms, hash=$hash"
done

echo "  Total Time: ${concurrent_total}ms"
echo ""

# Test 3: Verify all PNG files created
echo "Test 3: Verify PNG Files Created"
echo "------------------------------------------"

files_created=0
for hash in "${concurrent_hashes[@]}"; do
    if [ -f "$OUTPUT_DIR/$hash.png" ]; then
        size=$(stat -c%s "$OUTPUT_DIR/$hash.png" 2>/dev/null || stat -f%z "$OUTPUT_DIR/$hash.png" 2>/dev/null)
        echo "  ✓ File $hash.png exists (${size} bytes)"
        ((files_created++))
    else
        echo "  ✗ File $hash.png does not exist"
    fi
done
echo ""

# Test 4: Check for hash collisions
echo "Test 4: Check for Hash Collisions"
echo "------------------------------------------"

unique_hashes=$(printf '%s\n' "${concurrent_hashes[@]}" | sort -u | wc -l)
total_hashes=${#concurrent_hashes[@]}

if [ "$unique_hashes" -eq "$total_hashes" ]; then
    echo "  ✓ All $total_hashes hashes are unique (no collisions)"
else
    echo "  ✗ Hash collision detected! Only $unique_hashes unique hashes out of $total_hashes"
fi
echo ""

# Test 5: Performance comparison
echo "Test 5: Performance Comparison"
echo "------------------------------------------"

if [ "$concurrent_total" -gt 0 ]; then
    speedup=$(awk "BEGIN {printf \"%.2f\", $sequential_total / $concurrent_total}")
    echo "  Sequential Total: ${sequential_total}ms"
    echo "  Concurrent Total: ${concurrent_total}ms"
    echo "  Speedup: ${speedup}x"
    echo ""

    if awk "BEGIN {exit !($speedup > 0.8)}"; then
        echo "  ✓ Concurrent requests show performance benefit"
    else
        echo "  ⚠ Limited performance benefit from concurrency"
    fi
else
    echo "  ✗ Could not calculate speedup"
fi
echo ""

# Test 6: Response analysis
echo "Test 6: Response Analysis"
echo "------------------------------------------"

# Count successful requests
sequential_success=0
for code in "${sequential_results[@]}"; do
    if [ "$code" = "200" ]; then
        ((sequential_success++))
    fi
done

concurrent_success=0
for code in "${concurrent_http_codes[@]}"; do
    if [ "$code" = "200" ]; then
        ((concurrent_success++))
    fi
done

echo "  Sequential: $sequential_success/${#sequential_results[@]} requests succeeded"
echo "  Concurrent: $concurrent_success/${#concurrent_http_codes[@]} requests succeeded"
echo ""

# Cleanup
rm -rf "$TEMP_DIR"

# Final summary
echo "=========================================="
echo "Test Summary"
echo "=========================================="

all_passed=true

# Test 1: Sequential baseline
if [ "$sequential_success" -eq "${#sequential_results[@]}" ]; then
    echo "✓ Test 1: Sequential requests - PASSED"
else
    echo "✗ Test 1: Sequential requests - FAILED"
    all_passed=false
fi

# Test 2: Concurrent requests
if [ "$concurrent_success" -eq "${#concurrent_http_codes[@]}" ]; then
    echo "✓ Test 2: Concurrent requests - PASSED"
else
    echo "✗ Test 2: Concurrent requests - FAILED"
    all_passed=false
fi

# Test 3: File creation
if [ "$files_created" -eq "${#concurrent_hashes[@]}" ]; then
    echo "✓ Test 3: All PNG files created - PASSED"
else
    echo "✗ Test 3: All PNG files created - FAILED ($files_created/${#concurrent_hashes[@]})"
    all_passed=false
fi

# Test 4: Hash uniqueness
if [ "$unique_hashes" -eq "$total_hashes" ]; then
    echo "✓ Test 4: No hash collisions - PASSED"
else
    echo "✗ Test 4: No hash collisions - FAILED"
    all_passed=false
fi

# Test 5: Performance
if awk "BEGIN {exit !($speedup > 0.8)}"; then
    echo "✓ Test 5: Performance is reasonable - PASSED"
else
    echo "✗ Test 5: Performance is reasonable - FAILED"
    all_passed=false
fi

echo ""

if [ "$all_passed" = true ]; then
    echo "✓ ALL TESTS PASSED - Feature #43 verified"
    exit 0
else
    echo "✗ SOME TESTS FAILED - Feature #43 not fully verified"
    exit 1
fi
