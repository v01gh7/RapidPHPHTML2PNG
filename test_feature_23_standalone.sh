#!/bin/bash

echo "=== Feature #23: Library Selection Logging - Standalone Verification ==="
echo ""

# Test 1: Check if log file exists
echo "Test 1: Log file exists"
if [ -f "logs/library_selection.log" ]; then
    echo "  ✅ Log file exists: logs/library_selection.log"
    TEST1_PASS=1
else
    echo "  ❌ Log file does not exist"
    TEST1_PASS=0
fi
echo ""

# Test 2: Check if log file is readable
echo "Test 2: Log file is readable"
if [ -r "logs/library_selection.log" ]; then
    echo "  ✅ Log file is readable"
    TEST2_PASS=1
else
    echo "  ❌ Log file is not readable"
    TEST2_PASS=0
fi
echo ""

# Test 3: Check if log file is writable
echo "Test 3: Log file is writable"
if [ -w "logs/library_selection.log" ]; then
    echo "  ✅ Log file is writable"
    TEST3_PASS=1
else
    echo "  ❌ Log file is not writable"
    TEST3_PASS=0
fi
echo ""

# Test 4: Check if log contains timestamp
echo "Test 4: Log contains timestamp"
if grep -qE "\[20[0-9]{2}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}\]" logs/library_selection.log; then
    echo "  ✅ Log contains timestamp in format [YYYY-MM-DD HH:MM:SS]"
    TEST4_PASS=1
else
    echo "  ❌ Log does not contain timestamp"
    TEST4_PASS=0
fi
echo ""

# Test 5: Check if log contains "Selected Library"
echo "Test 5: Log contains 'Selected Library' marker"
if grep -q "Selected Library:" logs/library_selection.log; then
    echo "  ✅ Log contains 'Selected Library:' marker"
    TEST5_PASS=1
else
    echo "  ❌ Log does not contain 'Selected Library:' marker"
    TEST5_PASS=0
fi
echo ""

# Test 6: Check if log contains library name
echo "Test 6: Log contains library name"
if grep -q "Selected Library: \(GD\|WKHTMLTOIMAGE\|IMAGEMAGICK\)" logs/library_selection.log; then
    LIBRARY=$(grep "Selected Library:" logs/library_selection.log | tail -1 | sed 's/.*Selected Library: //' | sed 's/ .*//')
    echo "  ✅ Log contains library name: $LIBRARY"
    TEST6_PASS=1
else
    echo "  ❌ Log does not contain library name"
    TEST6_PASS=0
fi
echo ""

# Test 7: Check if log contains reason
echo "Test 7: Log contains reason for selection"
if grep -q "Reason:" logs/library_selection.log; then
    echo "  ✅ Log contains reason for selection"
    TEST7_PASS=1
else
    echo "  ❌ Log does not contain reason"
    TEST7_PASS=0
fi
echo ""

# Test 8: Check if log contains detection results
echo "Test 8: Log contains detection results"
if grep -q "Detection Results:" logs/library_selection.log; then
    echo "  ✅ Log contains detection results section"
    TEST8_PASS=1
else
    echo "  ❌ Log does not contain detection results"
    TEST8_PASS=0
fi
echo ""

# Test 9: Check if log shows availability status
echo "Test 9: Log shows library availability status"
if grep -q "AVAILABLE" logs/library_selection.log && grep -q "UNAVAILABLE" logs/library_selection.log; then
    echo "  ✅ Log shows both AVAILABLE and UNAVAILABLE statuses"
    TEST9_PASS=1
else
    echo "  ❌ Log does not show availability status"
    TEST9_PASS=0
fi
echo ""

# Test 10: Check if log includes detailed information
echo "Test 10: Log includes detailed library information"
if grep -q "Version:" logs/library_selection.log || grep -q "Info:" logs/library_selection.log; then
    echo "  ✅ Log includes detailed information for libraries"
    TEST10_PASS=1
else
    echo "  ❌ Log does not include detailed information"
    TEST10_PASS=0
fi
echo ""

# Summary
echo "=== Test Summary ==="
TOTAL_TESTS=10
PASSED_TESTS=$((TEST1_PASS + TEST2_PASS + TEST3_PASS + TEST4_PASS + TEST5_PASS + TEST6_PASS + TEST7_PASS + TEST8_PASS + TEST9_PASS + TEST10_PASS))
FAILED_TESTS=$((TOTAL_TESTS - PASSED_TESTS))

echo "Total Tests: $TOTAL_TESTS"
echo "✅ Passed: $PASSED_TESTS"
echo "❌ Failed: $FAILED_TESTS"
echo "Pass Rate: $(echo "scale=1; $PASSED_TESTS * 100 / $TOTAL_TESTS" | bc)%"
echo ""

if [ $PASSED_TESTS -eq $TOTAL_TESTS ]; then
    echo "🎉 All tests passed! Feature #23 is working correctly."
    exit 0
else
    echo "⚠️  Some tests failed. Please review the results above."
    exit 1
fi
