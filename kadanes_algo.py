"""Implement kadane's alogrith to solve the problem 
qt : 
Given an array Arr[] of N integers. Find the contiguous sub-array(containing at least one number) which has the maximum sum and return its sum.
"""

def k_a(arr):
    cs = 0 
    mx = arr[0]
    for i in range(len(arr)):
        cs += arr[i]
        if cs > mx:
            mx = cs
        if cs < 0 :
            cs = 0 
    return mx 

print(k_a([-1,-2,-3,-2,-5]))