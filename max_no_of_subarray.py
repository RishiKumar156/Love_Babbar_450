


def max_no_from_subarray(arr):
    res = max(arr)
    max_sum = min_sum = 1
    for n in arr:
        temp = max_sum * n 
        max_sum  = max(temp, min_sum * n , n)
        min_sum  = min(temp, min_sum * n , n)
        res = max(max_sum, min_sum, n)
    return res

print(max_no_from_subarray([8, -2, -2, 0, 8, 0, -6, -8, -6, -1]))